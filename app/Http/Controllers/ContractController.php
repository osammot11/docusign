<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\AuditService;
use App\Services\DocumentVault;
use App\Services\PdfInspector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    public function index()
    {
        $contracts = Contract::latest()->paginate(20);

        return view('contracts.index', compact('contracts'));
    }

    public function create()
    {
        return view('contracts.create');
    }

    public function store(Request $request, PdfInspector $inspector, DocumentVault $vault, AuditService $audit)
    {
        $data = $request->validate(['title' => 'required|string|max:180', 'company_name' => 'required|string|max:180', 'vat_number' => ['required', 'regex:/^[A-Z]{0,2}[0-9A-Z]{8,16}$/'], 'signer_name' => 'required|string|max:180', 'signer_role' => 'required|string|max:180', 'signer_email' => 'required|email:rfc|max:255', 'signer_pec' => 'nullable|email:rfc|max:255', 'expires_at' => 'required|date|after:now', 'document' => 'required|file|max:25600']);
        $pdf = $inspector->inspect($request->file('document'));
        $uuid = (string) Str::uuid();
        $path = "contracts/{$uuid}/v1/original.pdf.enc";
        $vault->put($path, $pdf['bytes']);
        unset($data['document']);
        $contract = Contract::create($data + ['uuid' => $uuid, 'public_id' => strtoupper(Str::random(16)), 'created_by' => $request->user()->id, 'source_path' => $path, 'source_hash' => $pdf['hash'], 'source_size' => $pdf['size'], 'source_pages' => $pdf['pages'], 'status' => ContractStatus::Draft]);
        $audit->record($contract, 'contract.created', ['version' => 1, 'source_hash' => $pdf['hash']], $request, 'admin', $request->user()->id);

        return redirect()->route('contracts.show', $contract)->with('success', 'Contratto creato.');
    }

    public function show(Contract $contract)
    {
        $contract->load(['signatures', 'auditEvents', 'mailDeliveries', 'invitations']);

        return view('contracts.show', compact('contract'));
    }

    public function document(Contract $contract, DocumentVault $vault)
    {
        return response($vault->get($contract->source_path), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="documento-'.$contract->public_id.'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function package(Contract $contract, DocumentVault $vault)
    {
        abort_unless($contract->package_path, 404);

        return response($vault->get($contract->package_path), 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="fascicolo-'.$contract->public_id.'.zip"', 'Cache-Control' => 'private, no-store']);
    }
}

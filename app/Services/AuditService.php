<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(?Contract $contract, string $event, array $payload = [], ?Request $request = null, string $actorType = 'system', ?int $actorId = null): AuditEvent
    {
        return DB::transaction(function () use ($contract, $event, $payload, $request, $actorType, $actorId) {
            $last = AuditEvent::query()->where('contract_id', $contract?->id)->lockForUpdate()->latest('id')->first();
            $createdAt = now('UTC');
            $body = [
                'contract_uuid' => $contract?->uuid,
                'event_type' => $event,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'payload' => $payload,
                'ip_address' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 1000),
                'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
                'created_at' => $createdAt->toIso8601String(),
                'previous_hash' => $last?->event_hash,
            ];

            return AuditEvent::query()->create([
                'contract_id' => $contract?->id,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'event_type' => $event,
                'payload' => $payload,
                'ip_address' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 1000),
                'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
                'previous_hash' => $last?->event_hash,
                'event_hash' => hash('sha256', CanonicalJson::encode($body)),
                'created_at' => $createdAt,
            ]);
        });
    }
}

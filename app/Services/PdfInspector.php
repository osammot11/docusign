<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use Throwable;

class PdfInspector
{
    public function inspect(UploadedFile $file): array
    {
        if ($file->getSize() > 25 * 1024 * 1024) {
            $this->fail('Il PDF supera 25 MB.');
        }
        $bytes = file_get_contents($file->getRealPath());
        if (! str_starts_with($bytes, '%PDF-')) {
            $this->fail('Il file non è un PDF valido.');
        }
        foreach (['/JavaScript', '/JS ', '/EmbeddedFile', '/OpenAction', '/AcroForm', '/Encrypt'] as $marker) {
            if (str_contains($bytes, $marker)) {
                $this->fail("Il PDF contiene una funzione non ammessa ({$marker}).");
            }
        }
        $qpdf = new Process(['qpdf', '--check', $file->getRealPath()]);
        try {
            $qpdf->run();
            if (! $qpdf->isSuccessful()) {
                $this->fail('qpdf segnala un PDF danneggiato o non conforme.');
            }
        } catch (Throwable) {
            // In sviluppo qpdf può non esserci: FPDI esegue comunque una validazione strutturale.
        }
        try {
            $pdf = new Fpdi;
            $pages = $pdf->setSourceFile($file->getRealPath());
        } catch (Throwable) {
            $this->fail('Il PDF è cifrato, danneggiato o usa una struttura non supportata.');
        }
        if ($pages < 1 || $pages > 200) {
            $this->fail('Il PDF deve contenere da 1 a 200 pagine.');
        }

        return ['bytes' => $bytes, 'hash' => hash('sha256', $bytes), 'size' => strlen($bytes), 'pages' => $pages];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}

@extends('layouts.app')

@section('title', 'Firma — '.$contract->title)

@section('content')
    <style>
        .otp-entry { padding: 22px; border: 2px solid var(--accent); border-radius: 12px; background: #fff8f3; scroll-margin-top: 96px; }
        .otp-entry h2 { margin-top: 6px; }
        .otp-entry input { font-size: 24px; letter-spacing: .22em; text-align: center; font-weight: 800; }
    </style>

    <div class="page-head">
        <div>
            <p class="eyebrow">Documento per {{ $contract->company_name }}</p>
            <h1>{{ $contract->title }}</h1>
            <p>Invitato: {{ $contract->signer_name }} · {{ $contract->signer_email }}</p>
        </div>
        <span class="badge large {{ $contract->status->value }}">{{ $contract->status->label() }}</span>
    </div>

    @if($contract->status === \App\Enums\ContractStatus::Completed)
        <section class="panel hero-success">
            <h2>Processo completato</h2>
            <p>Entrambe le parti hanno confermato il documento. Conserva il fascicolo probatorio.</p>
            <a class="button primary" href="{{ route('signer.package') }}">Scarica fascicolo ZIP</a>
            <a class="button secondary" href="{{ route('verify.show', $contract->public_id) }}">Verifica documento</a>
        </section>
    @elseif($contract->status === \App\Enums\ContractStatus::ClientSigned)
        <section class="panel">
            <h2>La tua conferma è stata registrata</h2>
            <p>Produce a Value deve ora controfirmare. Riceverai il fascicolo finale via email.</p>
        </section>
    @elseif(in_array($contract->status, [\App\Enums\ContractStatus::Declined, \App\Enums\ContractStatus::Expired, \App\Enums\ContractStatus::Voided]))
        <section class="panel">
            <h2>Il processo non è più disponibile</h2>
            <p>Stato: {{ $contract->status->label() }}. Contatta Produce a Value per chiarimenti.</p>
        </section>
    @else
        @php($awaitingOtp = session()->has('signer.confirmation'))

        <div class="grid signer-grid">
            <section class="panel document">
                <h2>1. Leggi il documento</h2>
                <iframe src="{{ route('signer.document') }}" title="Documento da firmare"></iframe>
                <a class="button secondary" target="_blank" href="{{ route('signer.document') }}">Apri o scarica il PDF</a>
                <p class="hash">SHA-256: {{ $contract->source_hash }}</p>
            </section>

            <section class="panel">
                @if($awaitingOtp)
                    <div id="otp" class="otp-entry">
                        <p class="eyebrow">Ultimo passaggio</p>
                        <h2>3. Inserisci il codice OTP</h2>
                        <p>Abbiamo inviato un codice di 6 cifre a <strong>{{ $contract->signer_email }}</strong>. Il codice è valido per 10 minuti.</p>
                        <form method="post" action="{{ route('signer.complete') }}" class="stack">
                            @csrf
                            <label>
                                Codice ricevuto via email
                                <input name="otp" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" maxlength="6" required autofocus>
                            </label>
                            <button class="primary">Conferma e firma</button>
                        </form>
                    </div>

                    <details>
                        <summary>Modifica i dati o richiedi un nuovo codice</summary>
                        @include('signer.partials.confirmation-form', ['contract' => $contract])
                    </details>
                @else
                    <h2>2. Conferma i dati</h2>
                    @include('signer.partials.confirmation-form', ['contract' => $contract])
                @endif

                <details>
                    <summary>Non intendo firmare</summary>
                    <form method="post" action="{{ route('signer.decline') }}" class="stack">
                        @csrf
                        <label>Motivazione<textarea name="reason" required></textarea></label>
                        <button class="danger">Registra il rifiuto</button>
                    </form>
                </details>
            </section>
        </div>
    @endif
@endsection

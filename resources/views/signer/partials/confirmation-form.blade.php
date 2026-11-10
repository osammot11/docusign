<form method="post" action="{{ route('signer.otp') }}" class="stack">
    @csrf
    <label>Nome e cognome<input name="name" value="{{ old('name', $contract->signer_name) }}" required></label>
    <label>Ruolo aziendale<input name="role" value="{{ old('role', $contract->signer_role) }}" required></label>
    <label class="check">
        <input type="checkbox" name="document_consent" value="1" required>
        Ho letto integralmente il PDF identificato dall’hash indicato.
    </label>
    <label class="check">
        <input type="checkbox" name="authority_consent" value="1" required>
        Dichiaro di avere i poteri per rappresentare {{ $contract->company_name }}.
    </label>
    <label class="check">
        <input type="checkbox" name="electronic_consent" value="1" required>
        Accetto espressamente il documento e l’uso di questo processo di firma elettronica semplice.
    </label>
    <label class="check">
        <input type="checkbox" name="privacy_acknowledged" value="1" required>
        <span>Ho letto l’<a target="_blank" href="{{ route('privacy') }}">informativa privacy</a>.</span>
    </label>
    <button class="primary">{{ session()->has('signer.confirmation') ? 'Invia un nuovo codice OTP' : 'Invia codice OTP' }}</button>
</form>

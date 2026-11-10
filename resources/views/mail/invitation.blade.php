<h1>Documento da leggere e firmare</h1>
<p>Produce a Value ti invita a esaminare il documento <strong>{{ $contract->title }}</strong> per {{ $contract->company_name }}.</p>
<p><a href="{{ $url }}">Apri il documento</a></p>
<p>Il link scade il {{ $contract->expires_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}. Non inoltrarlo ad altre persone.</p>
<p><small>Questo processo raccoglie una firma elettronica semplice e un dossier probatorio; non è una firma digitale o qualificata.</small></p>

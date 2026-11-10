<?php

return [
    'vault_key' => env('EVIDENCE_VAULT_KEY'),
    'signing_secret_key' => env('EVIDENCE_SIGNING_SECRET_KEY'),
    'signing_public_key' => env('EVIDENCE_SIGNING_PUBLIC_KEY'),
    'retention_years' => (int) env('EVIDENCE_RETENTION_YEARS', 10),
    'agency_name' => env('AGENCY_NAME', 'Produce a Value'),
    'agency_vat' => env('AGENCY_VAT'),
    'agency_pec' => env('AGENCY_PEC'),
    'public_host' => env('APP_URL', 'https://sign.produceavalue.com'),
];

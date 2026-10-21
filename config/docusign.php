<?php

return [
    'signing_certificate' => env(
        'DOCUSIGN_SIGNING_CERTIFICATE',
        storage_path('app/private/docusign-signing.crt')
    ),
    'signing_private_key' => env(
        'DOCUSIGN_SIGNING_PRIVATE_KEY',
        storage_path('app/private/docusign-signing.key')
    ),
    'signing_name' => env('DOCUSIGN_SIGNING_NAME', 'PEMA E-Sign'),
    'signing_organization' => env('DOCUSIGN_SIGNING_ORGANIZATION', 'PEMA'),
];

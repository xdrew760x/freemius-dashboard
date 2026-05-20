<?php

return [
    'api_base' => 'https://api.freemius.com/v1',

    // Each product needs its own credential set — Freemius bearer tokens are
    // plugin-scoped and can't authenticate requests for a different product.
    'products' => [
        [
            'id'     => 0,
            'label'  => 'Your Product',
            'bearer' => 'your_bearer_token_here',
            'pk'     => 'pk_your_public_key_here',
            'sk'     => 'sk_your_secret_key_here',
        ],
    ],
];

<?php

return [
    // Trust only the local tunnel connector after an explicit development opt-in.
    'proxies' => env('APP_ENV', 'production') === 'local'
        && env('TRUST_LOCAL_TUNNEL_PROXY', false) === true
            ? ['127.0.0.1', '::1']
            : [],
];

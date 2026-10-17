<?php

return [
    /*
    | Civil timezone for reports until account-level timezone preferences exist.
    | Timestamps keep the application's storage timezone (currently UTC).
    | Report boundaries are converted before querying; the global timezone is unchanged.
    */
    'timezone' => env('REPORTING_TIMEZONE', 'America/Porto_Velho'),
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenant base domain
    |--------------------------------------------------------------------------
    |
    | Salon workspaces are served as {domain}.{base}. Example:
    | ravibeautysalon.saloenza.com looks up "ravibeautysalon".
    |
    */

    'base_domain' => env('TENANT_BASE_DOMAIN', 'saloenza.com'),

    /*
    |--------------------------------------------------------------------------
    | Platform subdomains
    |--------------------------------------------------------------------------
    |
    | These labels are always available and are not salon workspaces.
    | app.saloenza.com is the shared Saloenza application.
    |
    */

    'platform_subdomains' => ['app', 'www', 'api'],

];

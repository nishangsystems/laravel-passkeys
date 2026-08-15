<?php

$allowedOrigins = env('PASSKEY_ALLOWED_ORIGINS');
$attestations = env('PASSKEY_ATTESTATIONS');

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that owns passkeys. This model should use the
    | NishangSystems\Passkeys\Traits\HasPasskeys trait.
    |
    */
    'user_model' => env('PASSKEY_USER_MODEL', App\Models\User::class),

    /*
    |--------------------------------------------------------------------------
    | Authentication Guard
    |--------------------------------------------------------------------------
    |
    | The guard used to protect passkey management routes (register, list, delete).
    | Login routes are public by design.
    |
    */
    'guard' => env('PASSKEY_GUARD', 'web'),

    /*
    |--------------------------------------------------------------------------
    | User Lookup Field
    |--------------------------------------------------------------------------
    |
    | The column used to look up a user during login option generation.
    |
    */
    'user_lookup_field' => env('PASSKEY_USER_LOOKUP_FIELD', 'email'),

    /*
    |--------------------------------------------------------------------------
    | Relying Party
    |--------------------------------------------------------------------------
    |
    | The WebAuthn relying party details. When rp_id is null, the host
    | component of config('app.url') is used.
    |
    */
    'rp_id' => env('PASSKEY_RP_ID', null),
    'rp_name' => env('PASSKEY_RP_NAME', config('app.name')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Origins that are permitted to perform WebAuthn ceremonies. These should
    | match the origins your frontend uses. Provide a comma separated list via
    | the environment variable, e.g.:
    | PASSKEY_ALLOWED_ORIGINS="http://localhost,https://example.com"
    |
    */
    'allowed_origins' => $allowedOrigins
        ? array_map('trim', explode(',', $allowedOrigins))
        : [
            'http://localhost',
            'http://localhost:8000',
            'http://localhost:5173',
            'https://' . parse_url(config('app.url'), PHP_URL_HOST),
        ],

    /*
    |--------------------------------------------------------------------------
    | Attestation Statement Support
    |--------------------------------------------------------------------------
    |
    | Enable additional attestation statement supports. By default only "none"
    | is enabled. Available options: "packed", "fido". Provide a comma separated
    | list via the environment variable, e.g.:
    | PASSKEY_ATTESTATIONS="packed,fido"
    |
    */
    'attestations' => $attestations
        ? array_map('trim', explode(',', $attestations))
        : [],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the routes registered by the package. Set "enabled" to false to
    | register your own routes and use the package controllers directly.
    |
    */
    'routes' => [
        'enabled' => env('PASSKEY_ROUTES_ENABLED', true),
        'prefix' => env('PASSKEY_ROUTE_PREFIX', ''),
        'middleware' => env('PASSKEY_ROUTE_MIDDLEWARE')
            ? array_map('trim', explode(',', env('PASSKEY_ROUTE_MIDDLEWARE')))
            : ['api'],
        'auth_middleware' => env('PASSKEY_ROUTE_AUTH_MIDDLEWARE')
            ? array_map('trim', explode(',', env('PASSKEY_ROUTE_AUTH_MIDDLEWARE')))
            : ['auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Active Check
    |--------------------------------------------------------------------------
    |
    | A callable or method name that receives the authenticated user and returns
    | true if the user may log in with a passkey. Set to null to disable the
    | check. This value cannot be set via environment variables because it is
    | executable code.
    |
    */
    'user_active_check' => null,
];

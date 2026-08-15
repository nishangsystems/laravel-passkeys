# Laravel Passkeys

A reusable Laravel package for WebAuthn passkey authentication.

## Features

- Register passkeys for authenticated users
- Authenticate users with passkeys
- List and delete passkeys
- Configurable user model, guard, and relying party
- Built on `web-auth/webauthn-lib`

## Installation

```bash
composer require nishangsystems/laravel-passkeys
```

Publish the configuration and migrations:

```bash
php artisan vendor:publish --tag=passkeys-config
php artisan vendor:publish --tag=passkeys-migrations
```

Run the migrations:

```bash
php artisan migrate
```

## Environment Variables

All package settings can be controlled via environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `PASSKEY_USER_MODEL` | `App\Models\User` | Eloquent model that owns passkeys |
| `PASSKEY_GUARD` | `web` | Authentication guard |
| `PASSKEY_USER_LOOKUP_FIELD` | `email` | Column used to look up users |
| `PASSKEY_RP_ID` | `null` | WebAuthn relying party ID |
| `PASSKEY_RP_NAME` | `config('app.name')` | WebAuthn relying party name |
| `PASSKEY_ALLOWED_ORIGINS` | `http://localhost,...` | Comma separated allowed origins |
| `PASSKEY_ATTESTATIONS` | *(empty)* | Comma separated attestation types |
| `PASSKEY_ROUTES_ENABLED` | `true` | Whether package routes are registered |
| `PASSKEY_ROUTE_PREFIX` | *(empty)* | Route prefix |
| `PASSKEY_ROUTE_MIDDLEWARE` | `api` | Comma separated public route middleware |
| `PASSKEY_ROUTE_AUTH_MIDDLEWARE` | `auth` | Comma separated protected route middleware |

Example `.env` entries:

```dotenv
PASSKEY_USER_MODEL=App\Models\Staff
PASSKEY_GUARD=staff-api
PASSKEY_ALLOWED_ORIGINS="http://localhost,https://app.example.com"
PASSKEY_ATTESTATIONS="packed"
```

## Configuration

Add the `HasPasskeys` trait to your user model:

```php
use NishangSystems\Passkeys\Traits\HasPasskeys;

class User extends Authenticatable
{
    use HasPasskeys;
}
```

## Routes

When routes are enabled, the package registers:

| Method | URI | Middleware | Description |
|--------|-----|------------|-------------|
| POST | `/auth/passkeys/options` | api | Get login options |
| POST | `/auth/passkeys` | api | Login with passkey |
| POST | `/passkeys/options` | api + auth | Get registration options |
| POST | `/passkeys` | api + auth | Register a passkey |
| GET | `/passkeys` | api + auth | List passkeys |
| DELETE | `/passkeys/{id}` | api + auth | Delete a passkey |

## Customization

### User active check

You can prevent disabled or terminated users from logging in by setting a callable or method name in `config/passkeys.php`:

```php
'user_active_check' => fn ($user) => $user->isActive(),
// or
'user_active_check' => 'isActive',
```

### Custom routes

Disable package routes and register your own:

```dotenv
PASSKEY_ROUTES_ENABLED=false
```

Then use the package controller directly:

```php
use NishangSystems\Passkeys\Http\Controllers\PasskeyController;

Route::post('/auth/passkeys/options', [PasskeyController::class, 'loginOptions']);
Route::post('/auth/passkeys', [PasskeyController::class, 'login']);

Route::middleware('auth')->group(function () {
    Route::post('/passkeys/options', [PasskeyController::class, 'registerOptions']);
    Route::post('/passkeys', [PasskeyController::class, 'register']);
    Route::delete('/passkeys/{id}', [PasskeyController::class, 'destroy']);
    Route::get('/passkeys', [PasskeyController::class, 'index']);
});
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT

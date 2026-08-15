# AGENTS.md — Laravel Passkeys

This file is a reference for AI coding agents working on the `nishangsystems/laravel-passkeys` package.

## Project overview

`nishangsystems/laravel-passkeys` is a reusable Laravel package that adds WebAuthn / passkey authentication to Laravel applications. It is built on top of `web-auth/webauthn-lib` (^5.0) and exposes a JSON API for registering passkeys, authenticating with passkeys, and managing a user's passkeys.

- **Package name:** `nishangsystems/laravel-passkeys`
- **Type:** Laravel library / package
- **PHP requirement:** `^8.2`
- **Laravel support:** `illuminate/*` `^11.0|^12.0`
- **Test framework:** PHPUnit `^10.0|^11.0` with Orchestra Testbench `^9.0|^10.0`
- **Primary namespace:** `NishangSystems\Passkeys\`
- **Service provider auto-discovery:** `NishangSystems\Passkeys\PasskeysServiceProvider`
- **Facade alias:** `Passkey` → `NishangSystems\Passkeys\Facades\Passkey`

## Directory structure

```
config/passkeys.php              Package configuration (all values env-driven)
database/migrations/             Migration that creates the polymorphic passkeys table
resources/lang/en/passkeys.php   Translation strings used by the package
routes/passkeys.php              Route definitions registered by the service provider
Dockerfile                       PHP 8.2 CLI image with PCOV for coverage
docker-compose.yml               Orchestrates the app container and a MySQL service
phpunit.xml                      PHPUnit configuration (forces CACHE_DRIVER=array)
phpmd.xml                        PHP Mess Detector ruleset
phpstan.neon                     Larastan / PHPStan configuration
testbench.yaml                   Orchestra Testbench workbench configuration
src/
  Contracts/
    PasskeyUser.php              Optional contract for user active checks
  Exceptions/
    PasskeyException.php         Domain exception thrown by the service layer
  Facades/
    Passkey.php                  Laravel facade for PasskeyService
  Http/
    Controllers/
      PasskeyController.php      API controller for all passkey endpoints
      RespondsWithJson.php       Trait wrapping success/failure JSON responses
    Requests/
      FormRequest.php            Base form request returning JSON validation errors
      GetPasskeyLoginOptionsRequest.php
      GetPasskeyRegisterOptionsRequest.php
      RegisterPasskeyRequest.php
      VerifyPasskeyRequest.php
    Resources/
      PasskeyResource.php        JSON resource returned for passkey list items
  Models/
    Passkey.php                  Eloquent model storing passkey data
  Services/
    PasskeyService.php           Core WebAuthn ceremony logic
  Traits/
    HasPasskeys.php              Adds the polymorphic `passkeys` relation to users
tests/
  TestCase.php                   Base test case using Orchestra Testbench
  TestUser.php                   Minimal Authenticatable model used in tests
  PasskeyControllerTest.php
  PasskeyServiceTest.php
```

## Runtime architecture

### Service provider

`PasskeysServiceProvider` performs the following:

1. `register()` merges `config/passkeys.php` into Laravel's config under the key `passkeys`.
2. `boot()`:
   - Publishes `config/passkeys.php` with the tag `passkeys-config`.
   - Publishes `database/migrations` with the tag `passkeys-migrations`.
   - Always loads the package migrations and translations.
   - Registers the routes in `routes/passkeys.php` only when `passkeys.routes.enabled` is `true`.

### Routes

When enabled, the package registers two route groups:

- Public group (`passkeys.routes.middleware`, default `api`):
  - `POST /auth/passkeys/options` — generate login options
  - `POST /auth/passkeys` — authenticate with a passkey
- Authenticated group (`auth_middleware` merged with `middleware`, default `auth`):
  - `POST /passkeys/options` — generate registration options
  - `POST /passkeys` — register a passkey
  - `GET /passkeys` — list the authenticated user's passkeys
  - `DELETE /passkeys/{id}` — delete one of the user's passkeys

The route prefix is controlled by `passkeys.routes.prefix`.

### Controller

`PasskeyController` handles HTTP concerns only:

- Uses `RespondsWithJson` for a consistent `{success, message, data/errors}` envelope.
- Generates a UUID-based cache key for each WebAuthn ceremony and stores the serialized options for 5 minutes.
- Delegates all WebAuthn validation to `PasskeyService`.
- Logs the user in using the configured guard (`passkeys.guard`, default `web`).
- If the user model has a `createToken` method (e.g. Sanctum), it includes a Bearer token in the login response.

### Service layer

`PasskeyService` wraps the `web-auth/webauthn-lib` library:

- `getLoginOptions($userId)` — returns `PublicKeyCredentialRequestOptions`, optionally scoped to a user's credentials.
- `getRegistrationOptions($userId, $userEmail, $displayName)` — returns `PublicKeyCredentialCreationOptions`.
- `verifyPasskey($passkey, $options, $host, $userHandle)` — validates an assertion and updates the stored credential counter.
- `getPublicKeyCredentialSource($passkey, $options, $host)` — validates an attestation and returns a `CredentialRecord`.
- `getCredentialId($source)` — returns a base64url-encoded credential ID.

The service relies on `Passkey::webAuthnSerializer()` and `Passkey::attestationStatementSupportManager()` for serialization and attestation support.

### Model

`NishangSystems\Passkeys\Models\Passkey`:

- Stores `name`, `credential_id`, and JSON-serialized WebAuthn credential `data`.
- Uses a polymorphic `owner` relation (`owner_type`, `owner_id`).
- Exposes static helpers for the shared WebAuthn serializer and attestation manager.
- Only `none` attestation is enabled by default; `packed` and `fido` can be enabled via `passkeys.attestations`.

The `HasPasskeys` trait should be added to the application's user model to expose the `passkeys()` morph-many relation.

## Configuration

All settings live in `config/passkeys.php` and can be overridden via environment variables:

| Config key | Environment variable | Default | Purpose |
|---|---|---|---|
| `user_model` | `PASSKEY_USER_MODEL` | `App\Models\User` | Eloquent model that owns passkeys |
| `guard` | `PASSKEY_GUARD` | `web` | Guard protecting management routes |
| `user_lookup_field` | `PASSKEY_USER_LOOKUP_FIELD` | `email` | Column used to look up users at login |
| `rp_id` | `PASSKEY_RP_ID` | `null` | WebAuthn relying party ID |
| `rp_name` | `PASSKEY_RP_NAME` | `config('app.name')` | WebAuthn relying party name |
| `allowed_origins` | `PASSKEY_ALLOWED_ORIGINS` | localhost variants + app URL host | Comma-separated origins allowed for ceremonies |
| `attestations` | `PASSKEY_ATTESTATIONS` | *(empty)* | Comma-separated extra attestation types (`packed`, `fido`) |
| `routes.enabled` | `PASSKEY_ROUTES_ENABLED` | `true` | Whether package routes are registered |
| `routes.prefix` | `PASSKEY_ROUTE_PREFIX` | *(empty)* | URL prefix for package routes |
| `routes.middleware` | `PASSKEY_ROUTE_MIDDLEWARE` | `api` | Comma-separated middleware for public routes |
| `routes.auth_middleware` | `PASSKEY_ROUTE_AUTH_MIDDLEWARE` | `auth` | Comma-separated middleware for protected routes |
| `user_active_check` | — | `null` | Callable or method name to verify a user can authenticate |

The relying party ID defaults to the host component of `config('app.url')` when not explicitly set.

## Build and test commands

Install dependencies:

```bash
composer install
```

Run the test suite locally:

```bash
vendor/bin/phpunit
```

Or via the composer script:

```bash
composer test
```

### Docker

A Docker Compose setup is provided for a consistent test environment. The `passkey-app` image is based on `php:8.2-cli` and includes Composer, PCOV (for code coverage), and the `zip` / `pdo_mysql` extensions.

Build and start the containers:

```bash
docker compose up -d
```

Install dependencies inside the container:

```bash
docker compose exec passkey-app composer install
```

Run the test suite with coverage inside the container:

```bash
docker compose exec passkey-app composer test
```

The current suite (9 tests, 22 assertions) passes against an in-memory SQLite database and reports code coverage via PCOV.

## Code style guidelines

The codebase follows these conventions. Keep new code consistent with them:

- **Namespace:** PSR-4 under `NishangSystems\Passkeys\`, mapped to `src/`.
- **Return types:** Declare scalar and object return types where possible.
- **Type hints:** Use typed properties and method parameters.
- **Controllers:** Keep thin; delegate business logic to `PasskeyService`. Use `RespondsWithJson` for responses.
- **Form requests:** Extend the package's `FormRequest` base class so validation failures are returned as JSON.
- **Translations:** User-facing messages are read from the `passkeys` translation namespace (`__('passkeys.key')`). Add new keys to `resources/lang/en/passkeys.php`.
- **Exceptions:** Use `NishangSystems\Passkeys\Exceptions\PasskeyException` for domain errors in the service layer. Controllers catch it and map it to a `400` failure response.
- **Configuration access:** Prefer `config('passkeys.key')` with sensible fallbacks; do not hard-code defaults in business logic.
- **Cache keys:** Use `reg_` and `log_` prefixes for registration and login ceremony cache entries.

## Testing instructions

- The base `TestCase` uses Orchestra Testbench.
- It creates a `test_users` table in memory, sets the package user model to `TestUser`, sets `cache.default` to `array`, and runs package migrations against the `testing` SQLite connection.
- Use `actingAs($user)` for authenticated routes; the guard configured in tests is `web`.
- Tests should target public endpoints without authentication and management endpoints with authentication.
- When adding a feature that touches the database, ensure the existing migration covers the schema or add a new migration in `database/migrations/`.
- Run the full suite before committing changes.

## Security considerations

When modifying or extending the package, keep the following in mind:

- **Allowed origins:** WebAuthn ceremonies are validated against `passkeys.allowed_origins`. In production this must contain only trusted HTTPS origins. Do not accept wildcard origins.
- **Relying party ID:** `passkeys.rp_id` should match the domain that serves the frontend. When left unset it is derived from `config('app.url')`; ensure `APP_URL` is accurate in production.
- **Ceremony state:** Registration and login options are stored in Laravel Cache for 5 minutes and referenced by a `session_id`. The controller pulls (deletes) the entry on verification to prevent replay; ensure cache is not shared across untrusted environments.
- **Host validation:** The service passes the request host (`$request->getHost()`) to the WebAuthn validator. Keep this aligned with the RP configuration.
- **User active check:** The optional `passkeys.user_active_check` setting can be a closure or a method name. It runs during login option generation and login. Be careful not to leak whether a user exists vs. is disabled through public responses.
- **Attestation:** Only `none` attestation is enabled by default. Enabling `packed` or `fido` requires appropriate trust decisions and attestation root certificates.
- **Credential storage:** Passkey public key material is stored in the `data` JSON column. Protect database backups accordingly; passkeys are bound to the application's origin and cannot be reused elsewhere, but the data is sensitive.
- **Route middleware:** Management routes require the configured auth middleware. Verify that custom route overrides preserve the intended protection.

## Common tasks

- **Add a new translation message:** edit `resources/lang/en/passkeys.php` and use `__('passkeys.key')` in code.
- **Change user model/guard:** update `.env` values (`PASSKEY_USER_MODEL`, `PASSKEY_GUARD`) or publish and edit `config/passkeys.php`.
- **Disable package routes:** set `PASSKEY_ROUTES_ENABLED=false` and register your own routes using `NishangSystems\Passkeys\Http\Controllers\PasskeyController`.
- **Add attestation support:** set `PASSKEY_ATTESTATIONS=packed,fido` and ensure the COSE/PKI dependencies are satisfied.

## Commit Conventions

Conventional Commits enforced by commitlint in CI. Allowed types:
`feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `build`, `ci`, `enh`, `enhance`, `tweak`, `imp`, `improve`

Rules:

- Header max 100 chars.
- Subject must be **sentence-case**: the first letter of the subject is uppercase, the rest of the subject is lowercase.
    - Do not use uppercase acronyms or PascalCase names in the subject (e.g., write `api`, `pdf`, `frontend_url`, `staff type` instead of `API`, `PDF`, `FRONTEND_URL`, `StaffType`).
- Scope is optional but recommended (e.g., `feat(auth): ...`).
- Body lines must not exceed 100 chars.
- No period at the end of the subject.

Note: All commit messages must pass the workflows/commit-message.yml workflow

Examples:

```
feat(auth): Add login endpoint

- Implement password-based login
- Add rate limiter
```

```
fix(reports): Resolve phpstan issues and add report tests

- Refactor ReportController to remove undefined properties
- Add ReportTest coverage
```

```
chore: Update docker compose for production
```

```
refactor(staff): Replace staff type model with enum

- Remove StaffType model, migration and controller
- Add EStaffType enum
```

## Code Style

- **PSR-12** enforced via Pint, PHP_CodeSniffer, and phpstan.
- **Line length**: 160 chars (configured in `phpcs.xml`).
- **PHPMD thresholds** (relaxed from defaults):
    - Cyclomatic complexity: 18
    - NPath complexity: 300
    - Excessive class complexity: 97
    - Max public methods: 20
- `app/Http/Resources/*` excluded from phpstan analysis.
- `StaticAccess`, `ElseExpression`, `CouplingBetweenObjects` (raised to 50) excluded in phpmd.

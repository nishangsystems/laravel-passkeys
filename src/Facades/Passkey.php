<?php

namespace NishangSystems\Passkeys\Facades;

use Illuminate\Support\Facades\Facade;
use NishangSystems\Passkeys\Services\PasskeyService;

/**
 * @method static \Webauthn\PublicKeyCredentialRequestOptions getLoginOptions(int|string|null $userId = null)
 * @method static \Webauthn\PublicKeyCredentialCreationOptions getRegistrationOptions(string $userId, string $userEmail, string $displayName)
 * @method static \NishangSystems\Passkeys\Models\Passkey verifyPasskey(array $passkey, string $options, string $host, ?string $userHandle = null)
 * @method static \Webauthn\CredentialRecord getPublicKeyCredentialSource(array $passkey, string $options, string $host)
 * @method static string getCredentialId(\Webauthn\CredentialRecord $publicKeyCredentialSource)
 *
 * @see \NishangSystems\Passkeys\Services\PasskeyService
 */
class Passkey extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PasskeyService::class;
    }
}

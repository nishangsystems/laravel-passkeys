<?php

namespace NishangSystems\Passkeys\Services;

use Illuminate\Support\Str;
use NishangSystems\Passkeys\Exceptions\PasskeyException;
use NishangSystems\Passkeys\Models\Passkey;
use NishangSystems\Passkeys\Traits\Loggable;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Exception\InvalidDataException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyService
{
    use Loggable;
    private function getRpId(): ?string
    {
        $configured = config('passkeys.rp_id');
        if ($configured !== null) {
            return $configured;
        }

        $domain = config('app.url');
        $host = parse_url($domain, PHP_URL_HOST);
        if ($host !== null) {
            return $host;
        }

        $host = parse_url('https://' . $domain, PHP_URL_HOST);
        return $host;
    }

    /**
     * @throws InvalidDataException
     */
    public function getLoginOptions(int|string|null $userId = null): PublicKeyCredentialRequestOptions
    {
        $allowedCredentials = [];

        if ($userId !== null) {
            $userModel = config('passkeys.user_model');
            $user = $userModel::find($userId);
            $allowedCredentials = $user
                ? $user->passkeys
                    ->map(function ($passkey) {
                        /** @var Passkey $passkey */
                        return $passkey->getCredential();
                    })
                    ->map(fn ($source) => $source->getPublicKeyCredentialDescriptor())
                    ->all()
                : [];
        }

        return new PublicKeyCredentialRequestOptions(
            challenge: Str::random(),
            rpId: $this->getRpId(),
            allowCredentials: $allowedCredentials,
        );
    }

    /**
     * @throws InvalidDataException
     */
    public function getRegistrationOptions(
        string $userId,
        string $userEmail,
        string $displayName
    ): PublicKeyCredentialCreationOptions {
        return new PublicKeyCredentialCreationOptions(
            rp: new PublicKeyCredentialRpEntity(
                name: config('passkeys.rp_name', config('app.name')),
                id: $this->getRpId(),
            ),
            user: new PublicKeyCredentialUserEntity(
                name: $userEmail,
                id: $userId,
                displayName: $displayName,
            ),
            challenge: Str::random(),
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws PasskeyException
     */
    public function verifyPasskey(array $passkey, string $options, string $host, ?string $userHandle = null): Passkey
    {
        $publicKeyCredential = Passkey::webAuthnSerializer()->deserialize(
            json_encode($passkey),
            PublicKeyCredential::class,
            'json'
        );

        $publicKeyCredentialOptions = Passkey::webAuthnSerializer()->deserialize(
            $options,
            PublicKeyCredentialRequestOptions::class,
            'json'
        );

        if (!$publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            throw new PasskeyException(__('passkeys.invalid_passkey'), 400);
        }

        $validatedPasskey = Passkey::firstWhere('credential_id', $this->base64urlEncode($publicKeyCredential->rawId));

        if (!$validatedPasskey) {
            throw new PasskeyException(__('passkeys.invalid_passkey'), 400);
        }

        try {
            $csmFactory = new CeremonyStepManagerFactory();
            $csmFactory->setAllowedOrigins($this->allowedOrigins());
            $csmFactory->setAttestationStatementSupportManager(Passkey::attestationStatementSupportManager());
            $publicKeyCredentialSource = AuthenticatorAssertionResponseValidator::create(
                $csmFactory->requestCeremony()
            )->check(
                credentialRecord: $validatedPasskey->getCredential(),
                authenticatorAssertionResponse: $publicKeyCredential->response,
                publicKeyCredentialRequestOptions: $publicKeyCredentialOptions,
                host: $host,
                userHandle: $userHandle,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            throw new PasskeyException(__('passkeys.invalid_passkey'), 400);
        }

        $validatedPasskey->update(['data' => Passkey::webAuthnSerializer()
            ->serialize($publicKeyCredentialSource, 'json')]);

        return $validatedPasskey;
    }

    /**
     * @throws ExceptionInterface
     * @throws PasskeyException
     * @throws \Throwable
     */
    public function getPublicKeyCredentialSource(array $passkey, string $options, string $host): CredentialRecord
    {
        $publicKeyCredential = Passkey::webAuthnSerializer()->deserialize(
            json_encode($passkey),
            PublicKeyCredential::class,
            'json'
        );

        $publicKeyCredentialOptions = Passkey::webAuthnSerializer()->deserialize(
            $options,
            PublicKeyCredentialCreationOptions::class,
            'json'
        );

        if (!$publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new PasskeyException(__('passkeys.invalid_passkey'), 400);
        }

        try {
            $csmFactory = new CeremonyStepManagerFactory();
            $csmFactory->setAllowedOrigins($this->allowedOrigins());
            $csmFactory->setAttestationStatementSupportManager(Passkey::attestationStatementSupportManager());
            $publicKeyCredentialSource = AuthenticatorAttestationResponseValidator::create(
                $csmFactory->creationCeremony(),
            )->check(
                authenticatorAttestationResponse: $publicKeyCredential->response,
                publicKeyCredentialCreationOptions: $publicKeyCredentialOptions,
                host: $host,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            throw new PasskeyException(__('passkeys.registration_failed'), 400);
        }

        return $publicKeyCredentialSource;
    }

    public function getCredentialId(CredentialRecord $publicKeyCredentialSource): string
    {
        return $this->base64urlEncode($publicKeyCredentialSource->publicKeyCredentialId);
    }

    /**
     * @return array<int, string>
     */
    private function allowedOrigins(): array
    {
        return config('passkeys.allowed_origins', []);
    }

    private function base64urlEncode(string $data): string
    {
        $b64 = base64_encode($data);

        return rtrim(strtr($b64, '+/', '-_'), '=');
    }
}

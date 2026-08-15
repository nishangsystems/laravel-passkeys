<?php

namespace NishangSystems\Passkeys\Tests;

use NishangSystems\Passkeys\Models\Passkey;
use NishangSystems\Passkeys\Services\PasskeyService;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

class PasskeyServiceTest extends TestCase
{
    private PasskeyService $service;
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasskeyService();
        $this->user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_can_generate_login_options(): void
    {
        $options = $this->service->getLoginOptions();

        $this->assertNotNull($options);
        $this->assertNotEmpty($options->challenge);
    }

    public function test_can_generate_login_options_for_user(): void
    {
        $credential = CredentialRecord::create(
            publicKeyCredentialId: 'cred_123',
            type: 'public-key',
            transports: [],
            attestationType: 'none',
            trustPath: new EmptyTrustPath(),
            aaguid: Uuid::v4(),
            credentialPublicKey: 'public_key_bytes',
            userHandle: (string) $this->user->id,
            counter: 0
        );

        $this->user->passkeys()->create([
            'name' => 'Test Key',
            'credential_id' => 'cred_123',
            'data' => Passkey::webAuthnSerializer()->serialize($credential, 'json'),
        ]);

        $options = $this->service->getLoginOptions($this->user->id);

        $this->assertCount(1, $options->allowCredentials);
    }

    public function test_can_generate_registration_options(): void
    {
        $options = $this->service->getRegistrationOptions(
            (string) $this->user->id,
            $this->user->email,
            $this->user->name
        );

        $this->assertNotNull($options);
        $this->assertEquals($this->user->email, $options->user->name);
    }

    public function test_attestation_manager_is_created(): void
    {
        $manager = Passkey::attestationStatementSupportManager();
        $this->assertNotNull($manager);
    }
}

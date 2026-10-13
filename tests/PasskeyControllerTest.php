<?php

namespace NishangSystems\Passkeys\Tests;

use Illuminate\Support\Facades\Cache;

class PasskeyControllerTest extends TestCase
{
    private TestUser $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function test_authenticated_user_can_get_register_options(): void
    {
        $response = $this->actingAs($this->user)->postJson('/passkeys/options', [
            'name' => 'Tester Key',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'options',
                    'session_id',
                ],
            ]);

        $this->assertNotNull(Cache::get($response->json('data.session_id')));
    }

    public function test_guest_can_get_login_options(): void
    {
        $response = $this->postJson('/auth/passkeys/options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'options',
                    'session_id',
                ],
            ]);
    }

    public function test_authenticated_user_can_list_passkeys(): void
    {
        $this->user->passkeys()->create([
            'name' => 'Work Laptop',
            'credential_id' => 'cred_1',
            'data' => json_encode(['data' => '1']),
        ]);

        $response = $this->actingAs($this->user)->getJson('/passkeys');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.passkeys');
    }

    public function test_authenticated_user_can_delete_own_passkey(): void
    {
        $passkey = $this->user->passkeys()->create([
            'name' => 'Old Passkey',
            'credential_id' => 'cred_to_delete',
            'data' => json_encode(['data' => 'to_delete']),
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/passkeys/{$passkey->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_unauthenticated_user_cannot_list_passkeys(): void
    {
        $response = $this->getJson('/passkeys');
        $response->assertStatus(401);
    }

    public function test_user_can_be_resolved_via_custom_get_authenticated_user_callback(): void
    {
        $customUser = TestUser::create([
            'name' => 'Custom User',
            'email' => 'custom@example.com',
        ]);

        $customUser->passkeys()->create([
            'name' => 'Custom Key',
            'credential_id' => 'cred_custom',
            'data' => json_encode(['data' => 'custom']),
        ]);

        config(['passkeys.get_authenticated_user' => fn () => $customUser]);

        $response = $this->actingAs($this->user)->getJson('/passkeys');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.passkeys');

        $this->assertSame('Custom Key', $response->json('data.passkeys.0.name'));
    }

    public function test_login_uses_default_response_data(): void
    {
        $passkey = $this->user->passkeys()->create([
            'name' => 'Default Key',
            'credential_id' => 'cred_default',
            'data' => json_encode(['data' => 'default']),
        ]);

        $mockService = \Mockery::mock(\NishangSystems\Passkeys\Services\PasskeyService::class);
        $mockService->shouldReceive('verifyPasskey')
            ->once()
            ->andReturn($passkey);
        $this->app->instance(\NishangSystems\Passkeys\Services\PasskeyService::class, $mockService);

        Cache::put('log_test_session', json_encode([
            'options' => '{"challenge":"test"}',
            'userHandle' => (string) $this->user->id,
        ]), 60);

        $response = $this->postJson('/auth/passkeys', [
            'session_id' => 'log_test_session',
            'passkey' => ['id' => 'cred_default', 'rawId' => 'cred_default'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $this->user->id,
                        'name' => $this->user->name,
                        'email' => $this->user->email,
                    ],
                ],
            ]);
    }

    public function test_login_uses_custom_response_formatter_when_configured(): void
    {
        $passkey = $this->user->passkeys()->create([
            'name' => 'Custom Formatter Key',
            'credential_id' => 'cred_custom_formatter',
            'data' => json_encode(['data' => 'custom_formatter']),
        ]);

        $mockService = \Mockery::mock(\NishangSystems\Passkeys\Services\PasskeyService::class);
        $mockService->shouldReceive('verifyPasskey')
            ->once()
            ->andReturn($passkey);
        $this->app->instance(\NishangSystems\Passkeys\Services\PasskeyService::class, $mockService);

        config(['passkeys.response_formatter' => function ($request, $user) {
            return [
                'user_id' => $user->id,
                'custom_token' => 'custom_token_123',
                'formatted' => true,
            ];
        }]);

        Cache::put('log_test_session_custom', json_encode([
            'options' => '{"challenge":"test"}',
            'userHandle' => (string) $this->user->id,
        ]), 60);

        $response = $this->postJson('/auth/passkeys', [
            'session_id' => 'log_test_session_custom',
            'passkey' => ['id' => 'cred_custom_formatter', 'rawId' => 'cred_custom_formatter'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->user->id,
                    'custom_token' => 'custom_token_123',
                    'formatted' => true,
                ],
            ])
            ->assertJsonMissing(['token_type' => 'Bearer']);
    }
}

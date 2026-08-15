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
}

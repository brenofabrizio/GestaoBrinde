<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_profile_permissions(): void
    {
        $this->seed(AccessSeeder::class);
        $user = User::factory()->create([
            'email' => 'operacao@example.test',
            'password' => 'secret-password',
            'role_id' => Role::query()->where('slug', 'operations')->value('id'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret-password',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role.slug', 'operations')
            ->assertJsonStructure(['token', 'token_type', 'user']);

        $tokenId = (int) explode('|', $response->json('token'), 2)[0];
        $this->assertNotNull(DB::table('personal_access_tokens')->where('id', $tokenId)->value('expires_at'));
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('auth_events', ['user_id' => $user->id, 'success' => true]);
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $this->seed(AccessSeeder::class);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'missing@example.test',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->assertDatabaseCount('auth_events', 5);
        $this->assertSame(hash('sha256', 'missing@example.test'), DB::table('auth_events')->value('email_hash'));
        $this->assertDatabaseHas('auth_events', ['success' => false]);
    }

    public function test_password_recovery_returns_generic_response_and_sends_reset_notification(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.test']);
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertAccepted()
            ->assertJsonPath('message', 'Se a conta existir, enviaremos instruções.');
        Notification::assertSentTo($user, ResetPassword::class);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])
            ->assertAccepted()
            ->assertJsonPath('message', 'Se a conta existir, enviaremos instruções.');
    }

    public function test_password_reset_revokes_tokens_and_clears_forced_change_flag(): void
    {
        $this->seed(AccessSeeder::class);
        $user = User::factory()->create(['must_change_password' => true]);
        $user->createToken('reset-test');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'fresh-password-987',
            'password_confirmation' => 'fresh-password-987',
        ])->assertOk();

        $this->assertTrue(Hash::check('fresh-password-987', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_forced_password_change_blocks_application_routes_until_password_is_changed(): void
    {
        $this->seed(AccessSeeder::class);
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'operations')->value('id'),
            'password' => 'old-password-123',
            'must_change_password' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/items')->assertForbidden();
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'old-password-123',
            'new_password' => 'new-secure-456',
            'new_password_confirmation' => 'new-secure-456',
        ])->assertOk();
        $this->assertFalse($user->fresh()->must_change_password);
        $this->getJson('/api/v1/items')->assertOk();
    }

    public function test_operations_profile_cannot_create_trade_request_permission(): void
    {
        $this->seed(AccessSeeder::class);
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'operations')->value('id'),
        ]);

        $this->assertFalse($user->hasPermission('requests.create'));
        $this->assertTrue($user->hasPermission('stock.entry'));
    }
}

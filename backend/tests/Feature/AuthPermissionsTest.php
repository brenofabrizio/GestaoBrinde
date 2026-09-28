<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

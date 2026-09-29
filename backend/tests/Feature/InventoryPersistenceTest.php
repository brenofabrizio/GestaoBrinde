<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessSeeder::class);
    }

    public function test_entry_and_exit_survive_a_new_read_request(): void
    {
        $user = User::factory()->create(['role_id' => Role::query()->where('slug', 'approver')->value('id')]);
        Sanctum::actingAs($user);
        $item = Item::query()->create([
            'code' => 'BRD-TEST-001',
            'name' => 'Brinde de teste',
            'category_id' => Category::query()->create(['name' => 'Testes'])->id,
            'status' => 'ativo',
        ]);

        $this->postJson("/api/v1/items/{$item->id}/entry", [
            'quantity' => 51,
            'reason' => 'Teste de entrada',
            'idempotency_key' => 'entry-test-001',
        ])->assertCreated();

        $this->getJson('/api/v1/items')->assertOk()->assertJsonPath('0.stock.qty_on_hand', 51);

        $this->postJson("/api/v1/items/{$item->id}/exit", [
            'quantity' => 6,
            'reason' => 'Teste de saída',
            'idempotency_key' => 'exit-test-001',
        ])->assertCreated();

        $this->getJson('/api/v1/items')->assertOk()->assertJsonPath('0.stock.qty_on_hand', 45);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_replaying_an_entry_idempotency_key_does_not_change_stock_twice(): void
    {
        $user = User::factory()->create(['role_id' => Role::query()->where('slug', 'approver')->value('id')]);
        Sanctum::actingAs($user);
        $item = Item::query()->create([
            'code' => 'BRD-IDEMPOTENCY-001',
            'name' => 'Brinde idempotente',
            'category_id' => Category::query()->create(['name' => 'Idempotência'])->id,
            'status' => 'ativo',
        ]);

        $payload = ['quantity' => 5, 'reason' => 'Teste de replay', 'idempotency_key' => 'entry-replay-001'];
        $this->postJson('/api/v1/items/'.$item->id.'/entry', $payload)->assertCreated();
        $this->postJson('/api/v1/items/'.$item->id.'/entry', $payload)->assertCreated();

        $this->assertDatabaseHas('stock', ['item_id' => $item->id, 'qty_on_hand' => 5]);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}

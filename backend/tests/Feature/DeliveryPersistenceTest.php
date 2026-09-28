<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_creates_protocol_and_debits_stock_once(): void
    {
        $this->seed(AccessSeeder::class);
        $requester = User::factory()->create(['role_id' => Role::query()->where('slug', 'requester')->value('id')]);
        $approver = User::factory()->create(['role_id' => Role::query()->where('slug', 'approver')->value('id')]);
        $operator = User::factory()->create(['role_id' => Role::query()->where('slug', 'operations')->value('id')]);
        $item = Item::query()->create(['code' => 'BRD-DELIVERY-001', 'name' => 'Brinde entrega', 'category_id' => Category::query()->create(['name' => 'Entregas'])->id]);
        Stock::query()->create(['item_id' => $item->id, 'qty_on_hand' => 10, 'qty_reserved' => 0]);

        Sanctum::actingAs($requester);
        $created = $this->postJson('/api/v1/trade-requests', [
            'purpose' => 'Entrega de teste', 'submit' => true,
            'items' => [['item_id' => $item->id, 'qty_requested' => 3]],
        ])->assertCreated();

        Sanctum::actingAs($approver);
        $this->postJson('/api/v1/trade-requests/' . $created->json('id') . '/approve', ['approved' => true])->assertOk();

        Sanctum::actingAs($operator);
        $payload = ['received_by_name' => 'Pessoa receptora', 'signature' => str_repeat('assinatura-', 3), 'idempotency_key' => 'delivery-test-001'];
        $delivery = $this->postJson('/api/v1/trade-requests/' . $created->json('id') . '/deliver', $payload)->assertCreated();
        $this->postJson('/api/v1/trade-requests/' . $created->json('id') . '/deliver', $payload)->assertCreated();

        $this->assertDatabaseHas('stock', ['item_id' => $item->id, 'qty_on_hand' => 7]);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('deliveries', ['id' => $delivery->json('id'), 'code' => 'PROT-' . now()->format('Y') . '-000001']);
    }
}

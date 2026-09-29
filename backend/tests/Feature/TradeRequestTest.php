<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Industry;
use App\Models\Item;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TradeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_submit_and_approver_can_decide(): void
    {
        $this->seed(AccessSeeder::class);
        $requester = User::factory()->create(['role_id' => Role::query()->where('slug', 'requester')->value('id')]);
        $approver = User::factory()->create(['role_id' => Role::query()->where('slug', 'approver')->value('id')]);
        $item = Item::query()->create([
            'code' => 'BRD-TRADE-001',
            'name' => 'Brinde TRADE',
            'category_id' => Category::query()->create(['name' => 'TRADE'])->id,
        ]);
        Stock::query()->create(['item_id' => $item->id, 'qty_on_hand' => 20, 'qty_reserved' => 0]);

        Sanctum::actingAs($requester);
        $created = $this->postJson('/api/v1/trade-requests', [
            'purpose' => 'Campanha de teste',
            'submit' => true,
            'items' => [['item_id' => $item->id, 'qty_requested' => 5]],
        ])->assertCreated()->assertJsonPath('status', 'aguardando_aprovacao');

        Sanctum::actingAs($approver);
        $this->postJson('/api/v1/trade-requests/'.$created->json('id').'/approve', [
            'approved' => true,
        ])->assertOk()->assertJsonPath('status', 'aprovada');

        $this->assertDatabaseHas('requests', ['id' => $created->json('id'), 'status' => 'aprovada']);
        $this->assertDatabaseCount('request_status_history', 3);
    }

    public function test_industry_user_cannot_create_request_for_another_industry(): void
    {
        $this->seed(AccessSeeder::class);
        $homeIndustry = Industry::query()->create(['name' => 'Indústria da conta']);
        $otherIndustry = Industry::query()->create(['name' => 'Outra indústria']);
        $requester = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'requester')->value('id'),
            'industry_id' => $homeIndustry->id,
        ]);
        $item = Item::query()->create([
            'code' => 'BRD-INDUSTRY-SCOPE',
            'name' => 'Brinde escopo',
            'category_id' => Category::query()->create(['name' => 'Escopo'])->id,
        ]);
        Sanctum::actingAs($requester);

        $this->postJson('/api/v1/trade-requests', [
            'purpose' => 'Solicitação fora do escopo',
            'industry_id' => $otherIndustry->id,
            'items' => [['item_id' => $item->id, 'qty_requested' => 1]],
        ])->assertForbidden();
        $this->assertDatabaseCount('requests', 0);
    }

    public function test_operations_profile_cannot_create_trade_request(): void
    {
        $this->seed(AccessSeeder::class);
        $user = User::factory()->create(['role_id' => Role::query()->where('slug', 'operations')->value('id')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/trade-requests', [
            'purpose' => 'Não permitido',
            'items' => [['item_id' => 1, 'qty_requested' => 1]],
        ])->assertForbidden();
    }
}

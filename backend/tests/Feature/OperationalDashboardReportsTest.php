<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\Industry;
use App\Models\Item;
use App\Models\Role;
use App\Models\Stock;
use App\Models\TradeRequest;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalDashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessSeeder::class);
    }

    public function test_dashboard_requires_permission_and_returns_industry_scoped_operational_totals(): void
    {
        $industry = Industry::query()->create(['name' => 'Conta A']);
        $otherIndustry = Industry::query()->create(['name' => 'Conta B']);
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'industry')->value('id'),
            'industry_id' => $industry->id,
        ]);
        $this->makeItem('A-1', 2, 5);
        $otherItem = $this->makeItem('B-1', 80, 0);
        $requestA = $this->makeRequest($user, $industry->id, 'aguardando_aprovacao', 'REQ-A');
        $requestB = $this->makeRequest($user, $otherIndustry->id, 'aguardando_aprovacao', 'REQ-B');
        $this->attachRequestItem($requestA, Item::query()->where('code', 'A-1')->first());
        $this->attachRequestItem($requestB, Item::query()->where('code', 'B-1')->first());
        $this->makeDelivery($user, $industry->id);
        $this->makeDelivery($user, $otherIndustry->id);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('stock.item_count', 1)
            ->assertJsonPath('stock.low_stock_count', 1)
            ->assertJsonPath('requests.pending_count', 1)
            ->assertJsonPath('deliveries.count', 1);

        $user->role->permissions()->detach();
        $this->getJson('/api/v1/dashboard')->assertForbidden();
    }

    public function test_reports_require_permission_apply_industry_filters_and_paginate(): void
    {
        $industry = Industry::query()->create(['name' => 'Conta A']);
        $otherIndustry = Industry::query()->create(['name' => 'Conta B']);
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'industry')->value('id'),
            'industry_id' => $industry->id,
        ]);
        $this->makeRequest($user, $industry->id, 'aguardando_aprovacao', 'REQ-A');
        $this->makeRequest($user, $otherIndustry->id, 'aguardando_aprovacao', 'REQ-B');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/requests?format=json&per_page=1')->assertOk()
            ->assertJsonPath('data.0.code', 'REQ-A')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1);

        $user->role->permissions()->detach();
        $this->getJson('/api/v1/reports/requests')->assertForbidden();
    }

    public function test_stock_and_delivery_reports_are_scoped_to_the_users_industry(): void
    {
        $industry = Industry::query()->create(['name' => 'Conta A']);
        $otherIndustry = Industry::query()->create(['name' => 'Conta B']);
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'industry')->value('id'),
            'industry_id' => $industry->id,
        ]);
        $ownItem = $this->makeItem('OWN-1', 4, 1);
        $otherItem = $this->makeItem('OTHER-1', 9, 1);
        $ownRequest = $this->makeRequest($user, $industry->id, 'aguardando_aprovacao', 'OWN-REQ');
        $otherRequest = $this->makeRequest($user, $otherIndustry->id, 'aguardando_aprovacao', 'OTHER-REQ');
        $this->attachRequestItem($ownRequest, $ownItem);
        $this->attachRequestItem($otherRequest, $otherItem);
        $this->makeDelivery($user, $industry->id);
        $this->makeDelivery($user, $otherIndustry->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/reports/stock')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'OWN-1');
        $this->getJson('/api/v1/reports/deliveries')->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.industry_id', $industry->id);
    }

    public function test_csv_report_neutralizes_formula_injection_and_rejects_unknown_report_types(): void
    {
        $industry = Industry::query()->create(['name' => 'Conta A']);
        $user = User::factory()->create([
            'role_id' => Role::query()->where('slug', 'industry')->value('id'),
            'industry_id' => $industry->id,
        ]);
        $this->makeRequest($user, $industry->id, 'aguardando_aprovacao', '=HYPERLINK("https://example.test")');
        Sanctum::actingAs($user);

        $csv = $this->get('/api/v1/reports/requests?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString("'=HYPERLINK", $csv->streamedContent());
        $this->getJson('/api/v1/reports/unknown')->assertNotFound();
        $this->getJson('/api/v1/reports/requests?format=xml')->assertUnprocessable();
    }

    private function makeItem(string $code, int $onHand, int $minimum): Item
    {
        $item = Item::query()->create([
            'code' => $code,
            'name' => 'Item '.$code,
            'category_id' => Category::query()->firstOrCreate(['name' => 'Geral'])->id,
            'min_stock' => $minimum,
        ]);
        Stock::query()->create(['item_id' => $item->id, 'qty_on_hand' => $onHand, 'qty_reserved' => 0]);

        return $item;
    }

    private function makeRequest(User $requester, int $industryId, string $status, string $code = 'REQ-TEST'): TradeRequest
    {
        return TradeRequest::query()->create([
            'code' => $code,
            'requester_id' => $requester->id,
            'industry_id' => $industryId,
            'purpose' => $code,
            'status' => $status,
        ]);
    }

    private function attachRequestItem(TradeRequest $request, Item $item): void
    {
        $request->items()->create(['item_id' => $item->id, 'qty_requested' => 1]);
    }

    private function makeDelivery(User $user, int $industryId): Delivery
    {
        return Delivery::query()->create([
            'code' => 'DEL-'.$industryId,
            'industry_id' => $industryId,
            'delivered_by' => $user->id,
            'received_by_name' => 'Recebedor',
            'idempotency_key' => 'DEL-KEY-'.$industryId,
        ]);
    }
}

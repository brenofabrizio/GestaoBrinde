<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Delivery;
use App\Models\Industry;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Stock;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_rejects_invalid_signature_data_uri(): void
    {
        $this->seed(AccessSeeder::class);
        $requester = User::factory()->create(['role_id' => Role::query()->where('slug', 'requester')->value('id')]);
        $item = Item::query()->create([
            'code' => 'BRD-DELIVERY-INVALID',
            'name' => 'Brinde assinatura',
            'category_id' => Category::query()->create(['name' => 'Entregas'])->id,
        ]);
        Stock::query()->create(['item_id' => $item->id, 'qty_on_hand' => 10, 'qty_reserved' => 0]);

        Sanctum::actingAs($requester);
        $created = $this->postJson('/api/v1/trade-requests', [
            'purpose' => 'Evento de teste',
            'items' => [['item_id' => $item->id, 'qty_requested' => 3]],
        ])->assertCreated();

        $operator = User::factory()->create(['role_id' => Role::query()->where('slug', 'operations')->value('id')]);
        Sanctum::actingAs($operator);
        $this->postJson('/api/v1/trade-requests/'.$created->json('id').'/deliver', [
            'received_by_name' => 'Pessoa receptora',
            'signature' => 'isso não é uma imagem',
            'idempotency_key' => 'delivery-invalid-signature',
        ])->assertUnprocessable()->assertJsonValidationErrors('signature');
    }

    public function test_delivery_creates_protocol_and_debits_stock_once(): void
    {
        Storage::fake('local');
        $this->seed(AccessSeeder::class);
        $requester = User::factory()->create(['role_id' => Role::query()->where('slug', 'requester')->value('id')]);
        $approver = User::factory()->create(['role_id' => Role::query()->where('slug', 'approver')->value('id')]);
        $operator = User::factory()->create(['role_id' => Role::query()->where('slug', 'operations')->value('id')]);
        $item = Item::query()->create([
            'code' => 'BRD-DELIVERY-001',
            'name' => 'Brinde entrega',
            'category_id' => Category::query()->create(['name' => 'Entregas'])->id,
        ]);
        Stock::query()->create(['item_id' => $item->id, 'qty_on_hand' => 10, 'qty_reserved' => 0]);

        Sanctum::actingAs($requester);
        $created = $this->postJson('/api/v1/trade-requests', [
            'purpose' => 'Entrega de teste', 'submit' => true,
            'items' => [['item_id' => $item->id, 'qty_requested' => 3]],
        ])->assertCreated();

        Sanctum::actingAs($approver);
        $this->postJson('/api/v1/trade-requests/'.$created->json('id').'/approve', ['approved' => true])->assertOk();

        Sanctum::actingAs($operator);
        $payload = ['received_by_name' => 'Pessoa receptora', 'signature' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/W5kAAAAASUVORK5CYII=', 'idempotency_key' => 'delivery-test-001'];
        $delivery = $this->postJson('/api/v1/trade-requests/'.$created->json('id').'/deliver', $payload)->assertCreated();
        $this->postJson('/api/v1/trade-requests/'.$created->json('id').'/deliver', $payload)->assertCreated();
        $this->postJson('/api/v1/trade-requests/'.$created->json('id').'/deliver', [
            ...$payload,
            'received_by_name' => 'Nome diferente',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('stock', ['item_id' => $item->id, 'qty_on_hand' => 7]);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('deliveries', ['id' => $delivery->json('id'), 'code' => 'PROT-'.now()->format('Y').'-000001']);

        $signaturePath = DB::table('deliveries')->where('id', $delivery->json('id'))->value('signature_path');
        $this->assertNotEmpty($signaturePath);
        Storage::disk('local')->assertExists($signaturePath);
        $this->assertSame(base64_decode(substr($payload['signature'], strlen('data:image/png;base64,')), true), Storage::disk('local')->get($signaturePath));

        $qr = $this->getJson('/api/v1/deliveries/'.$delivery->json('id').'/qr')->assertOk();
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $qr->json('data_uri'));

        $pdf = $this->get('/api/v1/deliveries/'.$delivery->json('id').'/pdf')->assertOk();
        $this->assertSame('attachment; filename="'.$delivery->json('code').'.pdf"', $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $ownerIndustry = Industry::query()->create(['name' => 'Indústria A']);
        $otherIndustry = Industry::query()->create(['name' => 'Indústria B']);
        Delivery::query()->whereKey($delivery->json('id'))->update(['industry_id' => $ownerIndustry->id]);
        $industryRole = Role::query()->where('slug', 'industry')->firstOrFail();
        $industryRole->permissions()->syncWithoutDetaching([
            Permission::query()->where('slug', 'deliveries.view')->value('id'),
        ]);
        $industryUser = User::factory()->create([
            'role_id' => $industryRole->id,
            'industry_id' => $otherIndustry->id,
        ]);
        Sanctum::actingAs($industryUser);
        $this->getJson('/api/v1/deliveries/'.$delivery->json('id'))->assertNotFound();
        $this->getJson('/api/v1/deliveries/'.$delivery->json('id').'/qr')->assertNotFound();
        $this->get('/api/v1/deliveries/'.$delivery->json('id').'/pdf')->assertNotFound();
    }
}

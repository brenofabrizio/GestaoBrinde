<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_event_and_create_allocation(): void
    {
        $this->seed(AccessSeeder::class);
        $user = User::factory()->create(['role_id' => Role::query()->where('slug', 'approver')->value('id')]);
        $industry = \App\Models\Industry::query()->create(['name' => 'Indústria evento']);
        $category = \App\Models\Category::query()->create(['name' => 'Eventos']);
        $item = \App\Models\Item::query()->create(['code' => 'BRD-EVENT-001', 'name' => 'Brinde evento', 'category_id' => $category->id]);
        Sanctum::actingAs($user);

        $event = $this->postJson('/api/v1/events', ['name' => 'Evento de teste'])->assertCreated();
        $this->postJson('/api/v1/events/' . $event->json('id') . '/open')->assertOk()->assertJsonPath('status', 'aberto');
        $this->postJson('/api/v1/events/' . $event->json('id') . '/allocations', [
            'industry_id' => $industry->id, 'item_id' => $item->id, 'qty_allocated' => 12,
        ])->assertCreated()->assertJsonPath('qty_allocated', 12);

        $this->assertDatabaseHas('event_allocations', ['event_id' => $event->json('id'), 'qty_allocated' => 12]);
    }
}

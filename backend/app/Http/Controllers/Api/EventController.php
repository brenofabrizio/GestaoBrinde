<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EventController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Event::query()->withCount('allocations')->latest()->paginate(25));
    }

    public function show(Event $event): JsonResponse
    {
        return response()->json($event->load('allocations'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:4000'],
            'venue' => ['nullable', 'string', 'max:190'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $event = Event::query()->create($data + ['status' => 'planejado']);
        return response()->json($event, 201);
    }

    public function open(Event $event): JsonResponse
    {
        abort_unless($event->status === 'planejado', 422, 'Somente eventos planejados podem ser abertos.');
        $event->update(['status' => 'aberto', 'opened_at' => now()]);
        return response()->json($event->fresh());
    }

    public function close(Event $event): JsonResponse
    {
        abort_unless($event->status === 'aberto', 422, 'Somente eventos abertos podem ser encerrados.');
        $event->update(['status' => 'encerrado', 'closed_at' => now()]);
        return response()->json($event->fresh());
    }

    public function allocate(Request $request, Event $event): JsonResponse
    {
        abort_unless(in_array($event->status, ['planejado', 'aberto'], true), 422, 'O evento não está disponível para cotas.');
        $data = $request->validate([
            'industry_id' => ['required', 'integer', 'exists:industries,id'],
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'qty_allocated' => ['required', 'integer', 'min:1'],
        ]);
        $allocation = DB::transaction(function () use ($event, $data) {
            $row = $event->allocations()->lockForUpdate()->firstOrNew([
                'industry_id' => $data['industry_id'],
                'item_id' => $data['item_id'],
            ]);
            $row->qty_allocated = $data['qty_allocated'];
            $row->qty_withdrawn ??= 0;
            abort_if($row->qty_allocated < $row->qty_withdrawn, 422, 'A cota não pode ser menor que o já retirado.');
            $row->save();
            return $row;
        });
        return response()->json($allocation, 201);
    }
}

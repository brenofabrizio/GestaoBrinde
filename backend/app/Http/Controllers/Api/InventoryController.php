<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class InventoryController extends Controller
{
    public function index(): JsonResponse
    {
        $items = Item::query()->with('stock')->where('status', 'ativo')->orderBy('name')->get();
        return response()->json($items);
    }

    public function movements(Request $request): JsonResponse
    {
        $rows = StockMovement::query()->with(['item:id,code,name', 'user:id,name'])
            ->latest('created_at')->paginate($request->integer('per_page', 25));
        return response()->json($rows);
    }

    public function entry(Request $request, Item $item, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document_ref' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:80', Rule::unique('stock_movements', 'idempotency_key')],
        ]);

        $movement = $inventory->entry($item, $data['quantity'], $request->user()?->id, $data);
        return response()->json($movement->load(['item', 'user']), 201);
    }

    public function exit(Request $request, Item $item, InventoryService $inventory): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document_ref' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:80', Rule::unique('stock_movements', 'idempotency_key')],
        ]);

        $movement = $inventory->exit($item, $data['quantity'], $request->user()?->id, $data);
        return response()->json($movement->load(['item', 'user']), 201);
    }
}

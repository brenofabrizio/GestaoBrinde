<?php

namespace App\Services;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryService
{
    public function entry(Item $item, int $quantity, ?int $userId, array $meta = []): StockMovement
    {
        return $this->move($item, $quantity, 'entrada', $userId, $meta);
    }

    public function exit(Item $item, int $quantity, ?int $userId, array $meta = []): StockMovement
    {
        return $this->move($item, -$quantity, 'saida', $userId, $meta);
    }

    private function move(Item $item, int $delta, string $type, ?int $userId, array $meta): StockMovement
    {
        if ($delta === 0) {
            throw ValidationException::withMessages(['quantity' => 'A quantidade deve ser maior que zero.']);
        }

        return DB::transaction(function () use ($item, $delta, $type, $userId, $meta): StockMovement {
            $lockedItem = Item::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
            DB::table('stock')->insertOrIgnore([
                'item_id' => $lockedItem->getKey(),
                'qty_on_hand' => 0,
                'qty_reserved' => 0,
                'updated_at' => now(),
            ]);
            $stock = $lockedItem->stock()->lockForUpdate()->firstOrFail();

            $available = (int) $stock->qty_on_hand - (int) $stock->qty_reserved;
            if ($delta < 0 && abs($delta) > $available) {
                throw ValidationException::withMessages([
                    'quantity' => "Estoque insuficiente. Disponível: {$available}.",
                ]);
            }

            if (!empty($meta['idempotency_key'])) {
                $existing = StockMovement::query()
                    ->where('idempotency_key', $meta['idempotency_key'])
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            $newBalance = (int) $stock->qty_on_hand + $delta;
            $stock->update(['qty_on_hand' => $newBalance, 'updated_at' => now()]);

            return StockMovement::query()->create([
                'item_id' => $lockedItem->getKey(),
                'type' => $type,
                'qty' => $delta,
                'balance_after' => $newBalance,
                'user_id' => $userId,
                'reason' => $meta['reason'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'document_ref' => $meta['document_ref'] ?? null,
                'request_id' => $meta['request_id'] ?? null,
                'delivery_id' => $meta['delivery_id'] ?? null,
                'event_id' => $meta['event_id'] ?? null,
                'industry_id' => $meta['industry_id'] ?? null,
                'idempotency_key' => $meta['idempotency_key'] ?? null,
            ]);
        });
    }
}

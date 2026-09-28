<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\TradeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DeliveryService
{
    public function deliverRequest(TradeRequest $request, User $user, array $data): Delivery
    {
        if (! $user->hasPermission('requests.process') && ! $user->hasPermission('stock.exit_confirm')) {
            abort(403, 'Você não tem permissão para confirmar esta retirada.');
        }

        return DB::transaction(function () use ($request, $user, $data): Delivery {
            $locked = TradeRequest::query()->with('items.item')->lockForUpdate()->findOrFail($request->id);
            $existing = Delivery::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing !== null) return $existing->load('items.item');
            if (! in_array($locked->status, ['aprovada', 'em_separacao', 'pronta'], true)) {
                abort(422, 'A solicitação ainda não está pronta para retirada.');
            }

            $delivery = Delivery::query()->create([
                'code' => 'TMP-' . Str::upper(Str::random(20)),
                'type' => 'dia_a_dia',
                'request_id' => $locked->id,
                'industry_id' => $locked->industry_id,
                'delivered_by' => $user->id,
                'received_by_name' => $data['received_by_name'],
                'received_by_document' => $data['received_by_document'] ?? null,
                'received_by_email' => $data['received_by_email'] ?? null,
                'received_by_phone' => $data['received_by_phone'] ?? null,
                'signature_hash' => hash('sha256', (string) ($data['signature'] ?? '')),
                'idempotency_key' => $data['idempotency_key'],
                'notes' => $data['notes'] ?? null,
            ]);
            $delivery->update(['code' => 'PROT-' . now()->format('Y') . '-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT), 'verification_hash' => hash('sha256', $delivery->id . '|' . $locked->id . '|' . $user->id)]);

            foreach ($locked->items as $line) {
                $qty = (int) ($line->qty_approved ?? $line->qty_requested) - (int) $line->qty_delivered;
                if ($qty <= 0) continue;
                $movement = app(InventoryService::class)->exit($line->item, $qty, $user->id, [
                    'reason' => 'Retirada de solicitação TRADE',
                    'request_id' => $locked->id,
                    'delivery_id' => $delivery->id,
                    'industry_id' => $locked->industry_id,
                    'recipient' => $data['received_by_name'],
                    'idempotency_key' => $data['idempotency_key'] . '-' . $line->item_id,
                ]);
                $delivery->items()->create(['item_id' => $line->item_id, 'qty' => $qty, 'balance_after' => $movement->balance_after]);
                $line->update(['qty_delivered' => (int) $line->qty_delivered + $qty]);
            }

            $locked->update(['status' => 'finalizada']);
            return $delivery->load(['items.item', 'request', 'deliveredBy']);
        });
    }
}

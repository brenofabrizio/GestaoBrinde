<?php

namespace App\Services;

use App\Models\RequestApproval;
use App\Models\TradeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TradeRequestService
{
    public function create(User $user, array $data, bool $submit = false): TradeRequest
    {
        if (! $user->hasPermission('requests.create')) abort(403, 'Você não tem permissão para criar solicitações TRADE.');
        $lines = $data['items'] ?? [];
        if (count($lines) === 0) abort(422, 'Inclua pelo menos um brinde na solicitação.');

        return DB::transaction(function () use ($user, $data, $lines, $submit): TradeRequest {
            $total = 0;
            $needsPurchase = false;
            $prepared = [];
            foreach ($lines as $line) {
                $item = \App\Models\Item::query()->with('stock')->findOrFail($line['item_id']);
                $qty = (int) $line['qty_requested'];
                $unit = (float) ($item->unit_value ?? 0);
                $available = (int) ($item->stock?->qty_on_hand ?? 0) - (int) ($item->stock?->qty_reserved ?? 0);
                $needsPurchase = $needsPurchase || $qty > $available;
                $total += $qty * $unit;
                $prepared[] = ['item_id' => $item->id, 'qty_requested' => $qty, 'unit_value' => $unit];
            }

            $request = TradeRequest::query()->create([
                'code' => 'TMP-' . Str::upper(Str::random(20)),
                'requester_id' => $user->id,
                'department_id' => $data['department_id'] ?? $user->department_id,
                'industry_id' => $data['industry_id'] ?? $user->industry_id,
                'purpose' => $data['purpose'],
                'recipient' => $data['recipient'] ?? null,
                'needed_date' => $data['needed_date'] ?? null,
                'purchase_ticket_no' => $data['purchase_ticket_no'] ?? null,
                'flow' => 'trade',
                'needs_purchase' => $needsPurchase,
                'status' => 'rascunho',
                'total_value' => round($total, 2),
                'notes' => $data['notes'] ?? null,
            ]);
            $request->update(['code' => 'SOL-' . now()->format('Y') . '-' . str_pad((string) $request->id, 6, '0', STR_PAD_LEFT)]);
            $request->items()->createMany($prepared);
            $this->history($request, null, 'rascunho', $user->id, 'Solicitação criada.');
            if ($submit) $this->submitLocked($request, $user);
            return $request->load(['items.item', 'history']);
        });
    }

    public function submit(TradeRequest $request, User $user): TradeRequest
    {
        return DB::transaction(function () use ($request, $user): TradeRequest {
            $locked = TradeRequest::query()->lockForUpdate()->findOrFail($request->id);
            $this->assertOwnerOrProcess($locked, $user);
            $this->submitLocked($locked, $user);
            return $locked->load(['items.item', 'history']);
        });
    }

    public function approve(TradeRequest $request, User $user, bool $approved, ?string $justification = null): TradeRequest
    {
        if (! $user->hasPermission('requests.approve')) abort(403, 'Você não tem permissão para aprovar solicitações.');
        return DB::transaction(function () use ($request, $user, $approved, $justification): TradeRequest {
            $locked = TradeRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($locked->status !== 'aguardando_aprovacao') abort(422, 'Esta solicitação não está aguardando aprovação.');
            $from = $locked->status;
            $changes = ['status' => $approved ? 'aprovada' : 'reprovada'];
            $changes[$approved ? 'approved_at' : 'rejected_at'] = now();
            $locked->update($changes);
            RequestApproval::query()->create([
                'request_id' => $locked->id,
                'approver_id' => $user->id,
                'decision' => $approved ? 'aprovado' : 'reprovado',
                'justification' => $justification,
                'decided_at' => now(),
            ]);
            $this->history($locked, $from, $locked->status, $user->id, $justification);
            return $locked->load(['items.item', 'history', 'approvals.approver']);
        });
    }

    public function canView(TradeRequest $request, User $user): bool
    {
        return $user->hasPermission('requests.view_all')
            || $request->requester_id === $user->id
            || ($user->industry_id !== null && $request->industry_id === $user->industry_id);
    }

    private function submitLocked(TradeRequest $request, User $user): void
    {
        if ($request->status !== 'rascunho') abort(422, 'Somente rascunhos podem ser enviados.');
        $from = $request->status;
        $request->update(['status' => 'aguardando_aprovacao', 'submitted_at' => now()]);
        $this->history($request, $from, $request->status, $user->id, 'Solicitação enviada para aprovação.');
    }

    private function assertOwnerOrProcess(TradeRequest $request, User $user): void
    {
        if ($request->requester_id !== $user->id && ! $user->hasPermission('requests.process')) {
            abort(403, 'Você não pode alterar esta solicitação.');
        }
    }

    private function history(TradeRequest $request, ?string $from, string $to, int $userId, ?string $comment): void
    {
        $request->history()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => $userId, 'comment' => $comment]);
    }
}

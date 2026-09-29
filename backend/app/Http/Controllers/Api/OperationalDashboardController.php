<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $industryId = $request->user()->industry_id;
        $requests = DB::table('requests')->whereNull('deleted_at');
        $deliveries = DB::table('deliveries');
        if ($industryId !== null) {
            $requests->where('industry_id', $industryId);
            $deliveries->where('industry_id', $industryId);
        }

        $stock = DB::table('stock')->join('items', 'items.id', '=', 'stock.item_id')->whereNull('items.deleted_at');
        $this->scopeStockItems($stock, $industryId);

        return response()->json([
            'stock' => [
                'item_count' => (clone $stock)->count(),
                'low_stock_count' => (clone $stock)->whereColumn('stock.qty_on_hand', '<=', 'items.min_stock')->count(),
                'quantity_on_hand' => (int) (clone $stock)->sum('stock.qty_on_hand'),
            ],
            'requests' => [
                'pending_count' => (clone $requests)->whereIn('status', ['aguardando_aprovacao', 'em_analise', 'aprovada'])->count(),
            ],
            'deliveries' => ['count' => $deliveries->count()],
        ]);
    }

    public function report(Request $request, string $type): JsonResponse|StreamedResponse
    {
        abort_unless(in_array($type, ['stock', 'requests', 'deliveries'], true), 404);
        $validated = $request->validate([
            'format' => ['sometimes', 'in:json,csv'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', 'max:35'],
            'type' => ['sometimes', 'string', 'max:20'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);
        $format = $validated['format'] ?? 'json';
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 25);
        $industryId = $request->user()->industry_id;

        if ($type === 'stock') {
            $query = DB::table('stock')->join('items', 'items.id', '=', 'stock.item_id')
                ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
                ->whereNull('items.deleted_at')
                ->select(['items.id', 'items.code', 'items.name', 'categories.name as category', 'items.min_stock', 'stock.qty_on_hand', 'stock.qty_reserved']);
            $this->scopeStockItems($query, $industryId);
        } elseif ($type === 'requests') {
            $query = DB::table('requests')->whereNull('deleted_at')
                ->select(['id', 'code', 'purpose', 'status', 'industry_id', 'needed_date', 'total_value', 'created_at']);
            if ($industryId !== null) {
                $query->where('industry_id', $industryId);
            }
            $this->applyCommonFilters($query, $validated, 'created_at');
        } else {
            $query = DB::table('deliveries')
                ->select(['id', 'code', 'type', 'industry_id', 'request_id', 'delivered_by', 'received_by_name', 'created_at']);
            if ($industryId !== null) {
                $query->where('industry_id', $industryId);
            }
            if (isset($validated['type'])) {
                $query->where('type', $validated['type']);
            }
            $dateFilters = $validated;
            unset($dateFilters['status'], $dateFilters['type']);
            $this->applyCommonFilters($query, $dateFilters, 'created_at');
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc($type === 'stock' ? 'items.id' : 'id')->forPage($page, $perPage)->get();
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows): void {
                $output = fopen('php://output', 'w');
                if ($rows->isNotEmpty()) {
                    fputcsv($output, array_keys((array) $rows->first()));
                    foreach ($rows as $row) {
                        fputcsv($output, array_map(fn (mixed $value): mixed => $this->safeCsvValue($value), (array) $row));
                    }
                }
                fclose($output);
            }, $type.'-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->json([
            'data' => $rows,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))],
        ]);
    }

    private function applyCommonFilters(Builder $query, array $filters, string $dateColumn): void
    {
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['from'])) {
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }
    }

    private function scopeStockItems(Builder $query, ?int $industryId): void
    {
        if ($industryId === null) {
            return;
        }

        $query->where(function (Builder $scoped) use ($industryId): void {
            $scoped->whereIn('items.id', DB::table('request_items')->join('requests', 'requests.id', '=', 'request_items.request_id')
                ->where('requests.industry_id', $industryId)->whereNull('requests.deleted_at')->select('request_items.item_id'))
                ->orWhereIn('items.id', DB::table('delivery_items')->join('deliveries', 'deliveries.id', '=', 'delivery_items.delivery_id')
                    ->where('deliveries.industry_id', $industryId)->select('delivery_items.item_id'));
        });
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value) || preg_match('/^\s*[=+\-@]/u', $value) !== 1) {
            return $value;
        }

        return "'".$value;
    }
}

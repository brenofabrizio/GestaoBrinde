<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\TradeRequest;
use App\Services\DeliveryService;
use App\Services\QrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Delivery::query()->with(['request:id,code,purpose', 'items.item:id,code,name'])->latest();
        if ($request->user()->industry_id !== null) $query->where('industry_id', $request->user()->industry_id);
        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function show(Delivery $delivery): JsonResponse
    {
        return response()->json($delivery->load(['request', 'items.item', 'deliveredBy:id,name']));
    }

    public function qr(Delivery $delivery, QrService $qr): JsonResponse
    {
        return response()->json([
            'code' => $delivery->code,
            'url' => $qr->signedUrl($delivery),
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ]);
    }

    public function verify(string $code): JsonResponse
    {
        $delivery = Delivery::query()->where('code', $code)->firstOrFail();
        return response()->json([
            'valid' => true,
            'protocol' => $delivery->code,
            'type' => $delivery->type,
            'created_at' => $delivery->created_at,
        ]);
    }

    public function store(Request $request, TradeRequest $tradeRequest, DeliveryService $service): JsonResponse
    {
        $data = $request->validate([
            'received_by_name' => ['required', 'string', 'max:150'],
            'received_by_document' => ['nullable', 'string', 'max:40'],
            'received_by_email' => ['nullable', 'email', 'max:190'],
            'received_by_phone' => ['nullable', 'string', 'max:40'],
            'signature' => ['required', 'string', 'min:20'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        return response()->json($service->deliverRequest($tradeRequest, $request->user(), $data), 201);
    }
}

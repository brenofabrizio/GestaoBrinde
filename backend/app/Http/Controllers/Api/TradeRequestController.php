<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TradeRequest;
use App\Services\TradeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TradeRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = TradeRequest::query()->with(['requester:id,name,email', 'items.item:id,code,name'])
            ->where('flow', 'trade')->latest();
        if (! $user->hasPermission('requests.view_all')) {
            $query->where(function ($scope) use ($user): void {
                $scope->where('requester_id', $user->id);
                if ($user->industry_id !== null) {
                    $scope->orWhere('industry_id', $user->industry_id);
                }
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function store(Request $request, TradeRequestService $service): JsonResponse
    {
        abort_unless($request->user()->active && $request->user()->hasPermission('requests.create'), 403, 'Você não tem permissão para criar solicitações TRADE.');

        $data = $request->validate([
            'purpose' => ['required', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'industry_id' => ['nullable', 'integer', 'exists:industries,id'],
            'needed_date' => ['nullable', 'date'],
            'purchase_ticket_no' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'submit' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct', 'exists:items,id'],
            'items.*.qty_requested' => ['required', 'integer', 'min:1'],
        ]);
        $created = $service->create($request->user(), $data, (bool) ($data['submit'] ?? false));

        return response()->json($created, 201);
    }

    public function show(Request $request, TradeRequest $tradeRequest, TradeRequestService $service): JsonResponse
    {
        abort_unless($service->canView($tradeRequest, $request->user()), 403);

        return response()->json($tradeRequest->load(['requester:id,name,email', 'items.item', 'history.user:id,name', 'approvals.approver:id,name']));
    }

    public function submit(Request $request, TradeRequest $tradeRequest, TradeRequestService $service): JsonResponse
    {
        return response()->json($service->submit($tradeRequest, $request->user()));
    }

    public function approve(Request $request, TradeRequest $tradeRequest, TradeRequestService $service): JsonResponse
    {
        $data = $request->validate(['approved' => ['required', 'boolean'], 'justification' => ['nullable', 'string', 'max:2000']]);

        return response()->json($service->approve($tradeRequest, $request->user(), $data['approved'], $data['justification'] ?? null));
    }
}

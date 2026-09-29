<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\TradeRequest;
use App\Services\DeliveryService;
use App\Services\QrService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class DeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Delivery::query()->with(['request:id,code,purpose', 'items.item:id,code,name'])->latest();
        if ($request->user()->industry_id !== null) {
            $query->where('industry_id', $request->user()->industry_id);
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }

    public function show(Request $request, Delivery $delivery): JsonResponse
    {
        $this->assertIndustryScope($request, $delivery);

        return response()->json($delivery->load(['request', 'items.item', 'deliveredBy:id,name']));
    }

    public function qr(Request $request, Delivery $delivery, QrService $qr): JsonResponse
    {
        $this->assertIndustryScope($request, $delivery);

        return response()->json([
            'code' => $delivery->code,
            'url' => $qr->signedUrl($delivery),
            'data_uri' => $qr->dataUri($delivery),
            'format' => 'svg',
            'expires_at' => now()->addDays(7)->toIso8601String(),
        ]);
    }

    public function pdf(Request $request, Delivery $delivery, QrService $qr): Response
    {
        $this->assertIndustryScope($request, $delivery);
        $delivery->load(['request.requester:id,name', 'items.item:id,code,name']);
        $signature = $delivery->signature_path
            ? Storage::disk('local')->get($delivery->signature_path)
            : null;
        $signatureImage = is_string($signature) && extension_loaded('gd')
            ? 'data:image/png;base64,'.base64_encode($signature)
            : null;

        $escape = static fn (?string $value): string => e($value ?? '—');
        $rows = $delivery->items->map(fn ($line): string => '<tr><td>'.$escape($line->item?->code)
            .'</td><td>'.$escape($line->item?->name).'</td><td>'.(int) $line->qty
            .'</td><td>'.(int) $line->balance_after.'</td></tr>')->implode('');
        $qrDataUri = $qr->dataUri($delivery);
        $signatureMarkup = $signatureImage
            ? '<img class="signature" src="'.$signatureImage.'" alt="Assinatura">'
            : '<p>Assinatura registrada — verificação: '.$escape($delivery->signature_hash).'</p>';

        $html = '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><style>'
            .'body{font-family:DejaVu Sans,sans-serif;color:#172033;font-size:12px}h1{font-size:22px}table{width:100%;border-collapse:collapse;margin:16px 0}th,td{border:1px solid #cbd5e1;padding:8px;text-align:left}th{background:#f1f5f9}.qr{width:130px}.signature{max-width:320px;max-height:120px;border-bottom:1px solid #333}'
            .'</style><h1>Protocolo de entrega '.$escape($delivery->code).'</h1><p><b>Data:</b> '
            .$escape($delivery->created_at?->format('d/m/Y H:i')).'</p><p><b>Solicitação:</b> '
            .$escape($delivery->request?->code).' — '.$escape($delivery->request?->purpose)
            .'</p><p><b>Recebido por:</b> '.$escape($delivery->received_by_name)
            .'</p><table><thead><tr><th>Código</th><th>Brinde</th><th>Quantidade</th><th>Saldo após</th></tr></thead><tbody>'
            .$rows.'</tbody></table><h2>Assinatura</h2>'.$signatureMarkup
            .'<h2>Verificação do protocolo</h2><img class="qr" src="'.$qrDataUri.'" alt="QR Code">'
            .'</html>';

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$delivery->code.'.pdf"',
        ]);
    }

    private function assertIndustryScope(Request $request, Delivery $delivery): void
    {
        $industryId = $request->user()?->industry_id;

        abort_if($industryId !== null && (int) $delivery->industry_id !== (int) $industryId, 404);
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
            'signature' => [
                'required',
                'string',
                'max:2000000',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $value, $matches) !== 1) {
                        $fail('A assinatura deve ser enviada como imagem PNG válida.');

                        return;
                    }

                    $image = base64_decode($matches[1], true);
                    $metadata = $image === false ? false : @getimagesizefromstring($image);

                    if (! is_array($metadata) || ($metadata['mime'] ?? null) !== 'image/png') {
                        $fail('A assinatura deve ser uma imagem PNG válida.');
                    }
                },
            ],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($service->deliverRequest($tradeRequest, $request->user(), $data), 201);
    }
}

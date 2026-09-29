<?php

namespace App\Services;

use App\Models\Delivery;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\URL;

final class QrService
{
    public function signedUrl(Delivery $delivery): string
    {
        return URL::temporarySignedRoute(
            'api.v1.verify',
            now()->addDays(7),
            ['code' => $delivery->code],
        );
    }

    public function dataUri(Delivery $delivery): string
    {
        $qrCode = new QrCode(
            data: $this->signedUrl($delivery),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 320,
            margin: 12,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255),
        );

        return (new SvgWriter)->write($qrCode)->getDataUri();
    }
}

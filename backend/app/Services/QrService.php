<?php

namespace App\Services;

use App\Models\Delivery;
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
}

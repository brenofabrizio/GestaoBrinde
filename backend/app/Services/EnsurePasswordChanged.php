<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $path = $request->path();

        if ($user?->must_change_password
            && ! str_ends_with($path, '/auth/change-password')
            && ! str_ends_with($path, '/auth/me')
            && ! str_ends_with($path, '/auth/logout')) {
            return response()->json([
                'message' => 'Altere sua senha antes de continuar.',
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return $next($request);
    }
}

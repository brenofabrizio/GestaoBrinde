<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->active || ! $user->hasPermission($permission)) {
            abort(403, 'Você não tem permissão para esta ação.');
        }

        return $next($request);
    }
}

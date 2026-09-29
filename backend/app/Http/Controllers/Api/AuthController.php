<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;

final class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->with(['role.permissions'])->where('email', $data['email'])->first();
        if ($user === null || ! $user->active || ! Hash::check($data['password'], $user->password)) {
            $this->recordAuthEvent($request, $user, false);

            return response()->json(['message' => 'Credenciais inválidas.'], 422);
        }

        $this->recordAuthEvent($request, $user, true);
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken(
            'gestao-brindes',
            ['*'],
            now()->addMinutes((int) config('auth.token_expiration_minutes', 480)),
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        PasswordBroker::sendResetLink(['email' => $data['email']]);

        return response()->json(['message' => 'Se a conta existir, enviaremos instruções.'], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $status = PasswordBroker::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();
            $user->tokens()->delete();
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return response()->json(['message' => 'O token de recuperação é inválido ou expirou.'], 422);
        }

        return response()->json(['message' => 'Senha redefinida. Entre novamente com a nova senha.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return response()->json([
                'message' => 'A senha atual está incorreta.',
                'errors' => ['current_password' => ['A senha atual está incorreta.']],
            ], 422);
        }

        $request->user()->forceFill([
            'password' => $data['new_password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Senha alterada com sucesso.',
            'user' => $request->user()->fresh()->load(['role.permissions', 'industry']),
        ]);
    }

    private function recordAuthEvent(Request $request, ?User $user, bool $success): void
    {
        DB::table('auth_events')->insert([
            'user_id' => $user?->id,
            'email_hash' => hash('sha256', mb_strtolower(trim((string) $request->input('email')), 'UTF-8')),
            'success' => $success,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load(['role.permissions', 'industry']));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sessão encerrada.']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\ResetOperationalFinancialData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OperationalDataResetController extends Controller
{
    private const SESSION_KEY = 'operational_data_reset.challenge';

    private const CHALLENGE_TTL_SECONDS = 300;

    private const CHALLENGE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function challenge(Request $request, ResetOperationalFinancialData $reset): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $code = $this->generateCode();

        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->getKey(),
            'hash' => hash('sha256', $code),
            'expires_at' => now()->addSeconds(self::CHALLENGE_TTL_SECONDS)->timestamp,
        ]);

        return response()->json([
            'code' => $code,
            'expires_in_seconds' => self::CHALLENGE_TTL_SECONDS,
            'counts' => $reset->preview($user),
        ]);
    }

    public function destroy(Request $request, ResetOperationalFinancialData $reset): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'password' => ['required', 'string'],
            'confirmation_code' => ['required', 'string', 'size:10', 'regex:/^[A-Z0-9]{10}$/'],
            'slider_confirmed' => ['required', 'accepted'],
        ]);

        if (! Hash::check((string) $validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'A senha informada não corresponde à sua senha atual.',
            ]);
        }

        $challenge = $request->session()->get(self::SESSION_KEY);
        $expired = ! is_array($challenge)
            || ! isset($challenge['user_id'], $challenge['hash'], $challenge['expires_at'])
            || (int) $challenge['user_id'] !== (int) $user->getKey()
            || (int) $challenge['expires_at'] < now()->timestamp;
        $matches = ! $expired && hash_equals(
            (string) $challenge['hash'],
            hash('sha256', strtoupper(trim((string) $validated['confirmation_code']))),
        );

        if (! $matches) {
            throw ValidationException::withMessages([
                'confirmation_code' => 'O código de confirmação expirou ou não corresponde ao exibido.',
            ]);
        }

        $request->session()->forget(self::SESSION_KEY);
        $reset->handle($user);

        return back()->with('success', 'Dados operacionais removidos. Sua estrutura básica foi preservada para um novo teste.');
    }

    private function generateCode(): string
    {
        $code = '';
        $maximumIndex = strlen(self::CHALLENGE_ALPHABET) - 1;

        for ($index = 0; $index < 10; $index++) {
            $code .= self::CHALLENGE_ALPHABET[random_int(0, $maximumIndex)];
        }

        return $code;
    }
}

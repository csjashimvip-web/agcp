<?php

namespace Modules\Identity\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passkeys\Passkey;

class PasskeyController
{
    public function index(Request $request): JsonResponse
    {
        $passkeys = $request->user()->passkeys()
            ->latest()
            ->get()
            ->map(fn (Passkey $passkey) => [
                'id' => $passkey->getKey(),
                'name' => $passkey->name,
                'last_used_at' => $passkey->last_used_at?->toAtomString(),
                'created_at' => $passkey->created_at?->toAtomString(),
            ]);

        return response()->json(['data' => $passkeys]);
    }

    public function destroy(Request $request, Passkey $passkey): JsonResponse
    {
        abort_unless((string) $passkey->user_id === (string) $request->user()->getKey(), 404);

        $passkey->delete();

        return response()->json(['message' => 'Passkey removed.']);
    }
}

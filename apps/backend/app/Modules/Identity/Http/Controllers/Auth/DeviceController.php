<?php

namespace Modules\Identity\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Identity\Http\Resources\DeviceResource;
use Modules\Identity\Infrastructure\Models\UserDevice;

class DeviceController
{
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->devices()->latest('last_seen_at')->limit(50)->get();

        return response()->json(['data' => DeviceResource::collection($devices)]);
    }

    public function trust(Request $request, UserDevice $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);

        $device->forceFill(['trusted_at' => now()])->save();

        return response()->json(['data' => new DeviceResource($device->refresh())]);
    }

    public function destroy(Request $request, UserDevice $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);

        $request->user()->authSessions()
            ->where('user_device_id', $device->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $device->delete();

        return response()->json(['message' => 'Device removed and its sessions revoked.']);
    }
}

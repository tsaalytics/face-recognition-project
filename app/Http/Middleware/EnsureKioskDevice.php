<?php

namespace App\Http\Middleware;

use App\Models\KioskDevice;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EnsureKioskDevice
{
    public function handle(Request $request, Closure $next)
    {
        $credential = $request->cookie(config('kiosk.cookie'), '');
        $parts = is_string($credential) ? explode('.', $credential, 2) : [];
        $device = count($parts) === 2 && Str::isUuid($parts[0]) && strlen($parts[1]) === 64
            ? KioskDevice::find($parts[0]) : null;
        if (! $device || ! $device->authorized() || ! hash_equals($device->token_hash, hash('sha256', $parts[1]))) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                return response()->json(['message' => 'Perangkat belum diaktifkan atau akses telah dicabut. Hubungi HR.'], 401);
            }
            return redirect()->route('kiosk.activate');
        }
        // This credential never logs in a User or grants access to any web role.
        $request->attributes->set('kiosk_device', $device);

        return $next($request);
    }
}

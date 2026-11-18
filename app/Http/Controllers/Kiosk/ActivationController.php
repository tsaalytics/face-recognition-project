<?php

namespace App\Http\Controllers\Kiosk;

use App\Http\Controllers\Controller;
use App\Models\KioskDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActivationController extends Controller
{
    public function show()
    {
        return response()->view('kiosk.activate')->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $local = app()->environment(['local', 'testing']) && in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true);
        abort_unless($request->isSecure() || $local, 403, 'Aktivasi perangkat memerlukan HTTPS. HTTP hanya diizinkan untuk localhost development.');
        $data = $request->validate(['activation_code' => ['required', 'string', 'max:24', 'regex:/^[a-zA-Z0-9 -]+$/D']]);
        $code = strtoupper(str_replace(['-', ' '], '', $data['activation_code']));
        [$device, $token] = DB::transaction(function () use ($code) {
            $device = KioskDevice::where('pairing_code_hash', hash('sha256', $code))->lockForUpdate()->first();
            if (! $device || $device->revoked_at || $device->activated_at || ! $device->pairing_expires_at?->isFuture()) {
                throw ValidationException::withMessages(['activation_code' => 'Kode tidak valid, sudah dipakai, atau kedaluwarsa. Minta kode baru dari HR.']);
            }
            $token = Str::random(64);
            $device->forceFill(['pairing_code_hash' => null, 'pairing_expires_at' => null, 'token_hash' => hash('sha256', $token),
                'activated_at' => now(), 'expires_at' => now()->addDays(config('kiosk.device_days'))])->save();

            return [$device, $token];
        });
        // Activating this browser as a kiosk must not leave a dashboard account open.
        if (Auth::check()) Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $cookie = cookie(config('kiosk.cookie'), $device->id.'.'.$token, config('kiosk.device_days') * 24 * 60,
            '/kiosk', null, $request->isSecure(), true, false, 'strict');

        return redirect()->route('kiosk.home')->cookie($cookie);
    }
}

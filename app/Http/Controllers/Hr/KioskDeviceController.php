<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\KioskDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class KioskDeviceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['per_page' => ['sometimes', 'integer', Rule::in([5, 10, 25, 50])]]);
        return response()->view('hr.kiosk-devices', ['devices' => KioskDevice::latest()->paginate((int) ($data['per_page'] ?? 10))->withQueryString()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $code = strtoupper(Str::random(16));
        (new KioskDevice)->forceFill(['name' => $data['name'], 'issued_by' => $request->user()->id,
            'pairing_code_hash' => hash('sha256', $code), 'pairing_expires_at' => now()->addMinutes(config('kiosk.pairing_minutes'))])->save();

        return redirect()->route('hr.kiosk-devices.index')->with('kiosk_pairing_code', encrypt(implode('-', str_split($code, 4))))
            ->with('success', 'Kode aktivasi siap. Masukkan di tablet melalui halaman /kiosk.');
    }

    public function revoke(KioskDevice $device)
    {
        $device->forceFill(['revoked_at' => now(), 'token_hash' => null, 'pairing_code_hash' => null])->save();

        return redirect()->route('hr.kiosk-devices.index')->with('success', 'Akses perangkat telah dicabut. Data absensi tetap tersimpan.');
    }
}

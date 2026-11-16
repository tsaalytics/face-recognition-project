<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\FaceAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FaceAttendanceController extends Controller
{
    public function show(Request $request)
    {
        return response()->view('hr.kiosk', ['expires' => $request->session()->get('face_kiosk_expires', 0)])->header('Cache-Control', 'private, no-store');
    }

    public function start(Request $request)
    {
        $request->session()->put('face_kiosk_expires', now()->addMinutes(15)->timestamp);

        return redirect()->route('hr.kiosk');
    }

    public function stop(Request $request)
    {
        $request->session()->forget('face_kiosk_expires');

        return redirect()->route('hr.kiosk');
    }

    public function store(Request $request, FaceAttendanceService $service)
    {
        $started = hrtime(true);
        abort_unless($request->isJson(), 415);
        abort_unless($request->session()->get('face_kiosk_expires', 0) > now()->timestamp, 403, 'Sesi kiosk berakhir. Aktifkan kembali melalui HR.');
        $data = $request->validate(['consent' => ['required', 'accepted'], 'type' => ['required', 'in:check_in,check_out'], 'samples' => ['required', 'array', 'size:1'], 'samples.0' => ['required', 'string', 'max:1400000']]);
        try {
            $result = $service->record($request->user(), $data, (int) $request->session()->get('face_kiosk_expires', 0));
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable) {
            return response()->json(['message' => 'Absensi gagal diproses. Periksa riwayat sebelum mencoba ulang.'], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json($result)->header('Cache-Control', 'no-store')
            ->header('Server-Timing', 'app;dur='.number_format((hrtime(true) - $started) / 1e6, 2, '.', ''));
    }
}

<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\FaceAttendanceService;
use App\Services\AttendanceSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FaceAttendanceController extends Controller
{
    public function show(Request $request)
    {
        return redirect()->route('hr.kiosk-devices.index');
    }

    protected function display(Request $request)
    {
        $paused = (bool) $request->session()->get('face_kiosk_paused', false);
        if (! $paused && $request->session()->get('face_kiosk_expires', 0) <= now()->timestamp) {
            $request->session()->put('face_kiosk_expires', now()->addMinutes(config('attendance.kiosk.session_minutes'))->timestamp);
        }

        return response()->view('kiosk.index', ['expires' => $request->session()->get('face_kiosk_expires', 0), 'paused' => $paused,
            'device' => $request->attributes->get('kiosk_device'),
            'schedule' => app(AttendanceSchedule::class)->current()])->header('Cache-Control', 'private, no-store');
    }

    protected function homeRoute(): string { return 'hr.kiosk'; }

    protected function actor(Request $request) { return $request->user(); }

    protected function requiresConsent(): bool { return true; }

    protected function lockKey(Request $request): string { return 'face-kiosk:'.hash('sha256', $request->session()->getId()); }

    public function start(Request $request)
    {
        $request->session()->forget('face_kiosk_paused');
        $request->session()->put('face_kiosk_expires', now()->addMinutes(config('attendance.kiosk.session_minutes'))->timestamp);

        if ($request->expectsJson()) return $this->status($request);

        return redirect()->route($this->homeRoute());
    }

    public function stop(Request $request)
    {
        $request->session()->forget('face_kiosk_expires');
        $request->session()->put('face_kiosk_paused', true);

        if ($request->expectsJson()) return response()->json(['paused' => true])->header('Cache-Control', 'no-store');

        return redirect()->route($this->homeRoute());
    }

    public function status(Request $request)
    {
        $schedule = app(AttendanceSchedule::class)->current();

        return response()->json(['expires' => $request->session()->get('face_kiosk_expires', 0),
            'paused' => (bool) $request->session()->get('face_kiosk_paused', false),
            'mode' => $schedule['mode'], 'attendance_date' => $schedule['attendance_date'],
            'server_time' => $schedule['time']->toIso8601String()])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, FaceAttendanceService $service)
    {
        $started = hrtime(true);
        abort_unless($request->isJson(), 415);
        abort_unless($request->session()->get('face_kiosk_expires', 0) > now()->timestamp, 403, 'Sesi kiosk berakhir. Aktifkan kembali melalui HR.');
        // Browser mode, timestamps and employee IDs are deliberately excluded.
        $data = $request->validate(array_merge($this->requiresConsent() ? ['consent' => ['required', 'accepted']] : [],
            ['samples' => ['required', 'array', 'size:1'], 'samples.0' => ['required', 'string', 'max:1400000']]));
        $lock = Cache::lock($this->lockKey($request), 180);
        if (! $lock->get()) {
            return response()->json(['message' => 'Recognition sebelumnya masih diproses. Tunggu sebentar.'], 429)->header('Retry-After', '7')->header('Cache-Control', 'no-store');
        }
        try {
            $result = $service->record($this->actor($request), $data, (int) $request->session()->get('face_kiosk_expires', 0));
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable) {
            return response()->json(['message' => 'Absensi gagal diproses. Periksa riwayat sebelum mencoba ulang.'], 503)->header('Cache-Control', 'no-store');
        } finally {
            $lock->release();
        }

        return response()->json($result)->header('Cache-Control', 'no-store')
            ->header('Server-Timing', 'app;dur='.number_format((hrtime(true) - $started) / 1e6, 2, '.', ''));
    }
}

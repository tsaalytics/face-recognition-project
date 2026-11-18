<x-kiosk-layout title="Kiosk Absensi Wajah">
<section class="kiosk-page">
<header class="kiosk-heading kiosk-heading-centered"><img src="{{ asset('images/facetrack-ai-logo.png') }}" alt="FaceTrack AI" width="180" height="43"></header>
<form data-face-kiosk action="{{ route('kiosk.scan') }}" data-status-url="{{ route('kiosk.status') }}" data-start-url="{{ route('kiosk.start') }}" data-stop-url="{{ route('kiosk.stop') }}" data-detector-base="{{ asset('vendor/kiosk-face-detector') }}" data-expires="{{ $expires }}" data-paused="{{ $paused ? '1' : '0' }}" data-mode="{{ $schedule['mode'] }}" data-server-time="{{ $schedule['time']->toIso8601String() }}" data-poll-ms="{{ config('attendance.kiosk.poll_ms') }}" data-stable-ms="{{ config('attendance.kiosk.stable_ms') }}" data-cooldown-ms="{{ config('attendance.kiosk.cooldown_ms') }}" data-absence-ms="{{ config('attendance.kiosk.absence_ms') }}" class="kiosk-card">
@csrf
<div class="kiosk-meta"><span data-camera-state>Camera Inactive</span><time data-kiosk-time>{{ $schedule['time']->format('d M Y H:i:s') }} WIB</time><strong data-kiosk-mode>{{ $schedule['mode'] }}</strong></div>
<div class="kiosk-preview"><video autoplay muted playsinline aria-label="Live camera preview absensi"></video><div class="kiosk-face-guide" aria-hidden="true"></div></div><canvas hidden></canvas>
<p data-status role="status" aria-live="polite" class="sr-only">Menyiapkan kiosk otomatis…</p>
<noscript>Aktifkan JavaScript untuk menggunakan kiosk. Hubungi HR untuk koreksi absensi.</noscript>
</form>
<dialog class="kiosk-result-dialog" data-result-dialog aria-labelledby="kiosk-result-title" aria-describedby="kiosk-result-message">
    <div class="kiosk-popup-icon" data-result-icon aria-hidden="true"></div>
    <h2 id="kiosk-result-title" data-result-title></h2>
    <p id="kiosk-result-message" data-result></p>
    <span class="kiosk-popup-badge" data-result-badge hidden>Terlambat</span>
    <div class="kiosk-popup-actions"><button type="button" class="list-control" data-result-close>Tutup</button><button type="button" class="list-control list-primary" data-result-retry>Coba Lagi</button></div>
</dialog>
</section>
</x-kiosk-layout>

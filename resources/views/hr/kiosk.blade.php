<x-layout title="Kiosk Absensi Wajah">
<section class="kiosk-page">
<header class="kiosk-heading"><div><h1>Kiosk Absensi Wajah</h1><p>Absensi otomatis berdasarkan jadwal server WIB.</p></div><img src="{{ asset('images/facetrack-ai-logo.png') }}" alt="FaceTrack AI" width="150" height="36"></header>
<form data-face-kiosk action="{{ route('hr.kiosk.scan') }}" data-status-url="{{ route('hr.kiosk.status') }}" data-start-url="{{ route('hr.kiosk.start') }}" data-stop-url="{{ route('hr.kiosk.stop') }}" data-detector-base="{{ asset('vendor/kiosk-face-detector') }}" data-expires="{{ $expires }}" data-paused="{{ $paused ? '1' : '0' }}" data-mode="{{ $schedule['mode'] }}" data-server-time="{{ $schedule['time']->toIso8601String() }}" data-poll-ms="{{ config('attendance.kiosk.poll_ms') }}" data-stable-ms="{{ config('attendance.kiosk.stable_ms') }}" data-cooldown-ms="{{ config('attendance.kiosk.cooldown_ms') }}" data-absence-ms="{{ config('attendance.kiosk.absence_ms') }}" class="kiosk-card">
@csrf
<div class="kiosk-meta"><span data-camera-state>Camera Inactive</span><time data-kiosk-time>{{ $schedule['time']->format('d M Y H:i:s') }} WIB</time><strong data-kiosk-mode>{{ $schedule['mode'] }}</strong></div>
<div class="kiosk-preview"><video autoplay muted playsinline aria-label="Live camera preview absensi"></video><div class="kiosk-face-guide" aria-hidden="true"></div></div><canvas hidden></canvas>
<p data-status role="status" aria-live="polite" class="kiosk-status">Menyiapkan kiosk otomatis…</p>
<label class="profile-consent"><input type="checkbox" data-consent> Saya menyetujui pemrosesan wajah untuk mencatat absensi demo ini (biometric-demo-v1). Setelah persetujuan, scanning berlangsung otomatis.</label>
<p data-result role="status" aria-live="polite" class="kiosk-result"></p>
<div class="kiosk-actions"><button type="button" data-kiosk-toggle class="list-control">Jeda Kiosk</button><button type="button" data-kiosk-retry class="list-control" hidden>Coba Kamera Lagi</button><a class="list-control" href="{{ route('hr.corrections.index') }}">Koreksi melalui HR</a></div>
<noscript>Aktifkan JavaScript untuk menggunakan kiosk. HR dapat memakai workflow koreksi absensi.</noscript>
</form>
<p class="kiosk-privacy">Kiosk demo nonkomersial dengan pengawasan HR. HR memastikan peserta menerima penjelasan dan menyetujui pemrosesan biometrik (biometric-demo-v1). Peserta dapat menolak dan memakai koreksi melalui HR. Foto diproses sementara tanpa disimpan permanen. Deteksi lokal hanya memicu pemindaian; tidak ada liveness/anti-spoofing. Kosongkan bingkai setelah hasil tampil.</p>
</section>
</x-layout>

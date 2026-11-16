<x-layout title="Kiosk absensi wajah">
<h1 class="text-3xl font-bold text-purple">Kiosk absensi wajah</h1>
<p class="mt-4 text-sm">Demo nonkomersial dengan pengawasan HR. Sesi regular, mulai 08.00 WIB tanpa toleransi keterlambatan. Waktu absensi berasal dari server. Tidak ada liveness/anti-spoofing. Foto diproses sementara tanpa disimpan. Peserta dapat menolak biometrik dan menggunakan koreksi melalui HR.</p>
@if($expires > now()->timestamp)
<p class="mt-4">Sesi berakhir {{ \Carbon\Carbon::createFromTimestamp($expires, 'Asia/Jakarta')->format('H:i:s') }} WIB.</p>
<form method="POST" action="{{ route('hr.kiosk.stop') }}" class="mt-4">@csrf<button class="button">Tutup sesi kiosk</button></form>
<form data-face-kiosk data-expires="{{ $expires }}" action="{{ route('hr.kiosk.scan') }}" class="mt-6 rounded-2xl bg-white p-6">
@csrf
<label class="block"><input type="checkbox" data-consent> Saya menyetujui pemrosesan wajah untuk mencatat absensi demo ini (biometric-demo-v1).</label>
<label class="mt-4 block">Jenis absensi <select name="type"><option value="check_in">Check-in</option><option value="check_out">Check-out</option></select></label>
<video autoplay muted playsinline class="mt-4 aspect-video w-full max-w-xl bg-slate-900" aria-label="Preview kamera absensi"></video><canvas hidden></canvas>
<button type="button" data-start class="button mt-4">Buka kamera</button>
<button data-scan class="button mt-4" disabled>Scan dan catat absensi</button>
<p role="status" aria-live="polite" class="mt-4" data-status>Kamera belum aktif.</p>
<noscript>Aktifkan JavaScript untuk menggunakan kamera.</noscript>
</form>
@else
<form method="POST" action="{{ route('hr.kiosk.start') }}" class="mt-6">@csrf<button class="button">Aktifkan kiosk selama 15 menit</button></form>
@endif
</x-layout>

<x-layout title="Ajukan Koreksi">
<a class="reset-link" href="{{ route('employee.corrections.index') }}">&larr; Kembali ke koreksi saya</a>
<h1 class="mt-4 text-3xl font-bold text-purple">Ajukan koreksi absensi</h1>
<p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">Tanggal dan jam absensi koreksi diambil dari waktu server saat dikirim (Asia/Jakarta), bukan waktu persetujuan HR. Gunakan permohonan ini untuk peninjauan kehadiran oleh HR. AI belum diaktifkan. Foto, lokasi, dan timestamp perangkat tidak menjamin identitas atau kehadiran; HR menentukan keputusan akhir.</p>
<noscript><p role="alert" class="mt-5 rounded-xl bg-amber-50 p-4">JavaScript diperlukan untuk kamera dan lokasi. Hubungi HR untuk prosedur alternatif.</p></noscript>
<form method="POST" enctype="multipart/form-data" action="{{ route('employee.corrections.store') }}" class="mt-6 rounded-2xl bg-white p-6 md:p-8" data-correction-form>
@csrf
<div role="alert" tabindex="-1" class="mb-5 hidden whitespace-pre-line rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" data-correction-errors></div>
<div class="grid gap-5 md:grid-cols-3">
<div><label class="filter-label" for="type">Jenis absensi</label><select class="field" name="type" id="type" required><option value="check_in" @selected(old('type')==='check_in')>Check In</option><option value="check_out" @selected(old('type')==='check_out')>Check Out</option></select></div>


</div>
<div class="mt-5"><label class="filter-label" for="reason">Alasan koreksi / gagal absensi (10-2000 karakter)</label><textarea class="field" id="reason" name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></div>
<section class="mt-6 rounded-xl bg-lavender p-5"><h2 class="font-semibold text-purple">Privasi dan persetujuan</h2><p class="mt-2 text-sm leading-6 text-slate-600">Foto dari kamera, waktu pengambilan, dan koordinat lokasi akan dikirim bersama permohonan untuk ditinjau HR, disimpan secara privat, dan dapat dilihat Anda serta HR. Kamera dan lokasi hanya diminta setelah Anda menekan tombol di bawah. Anda dapat menghentikan kamera dan meninggalkan formulir sebelum mengirim.</p><p class="mt-2 text-sm leading-6 text-slate-600">Jika tidak bersedia atau izin ditolak, permohonan ini tidak dapat dikirim. Hubungi HR untuk peninjauan manual melalui prosedur alternatif perusahaan. Tidak ada lokasi atau foto pengganti yang dibuat otomatis.</p>
<label class="mt-4 flex items-start gap-3 text-sm"><input class="mt-1 accent-magenta" type="checkbox" name="privacy_consent" value="1" required data-privacy-consent><span>Saya memahami penggunaan foto dan lokasi untuk peninjauan permohonan ini.</span></label>
</section>
<div class="mt-6 grid gap-6 lg:grid-cols-2">
<section><h2 class="font-semibold text-purple">1. Ambil foto langsung</h2><p class="mt-2 text-xs text-slate-500">Gunakan perangkat dengan kamera pada HTTPS atau localhost. Foto tidak dipakai untuk face recognition.</p>
<video class="mt-4 hidden max-h-80 w-full rounded-xl bg-slate-900 object-contain" autoplay muted playsinline data-camera-video></video>
<img class="mt-4 hidden max-h-80 w-full rounded-xl object-contain" alt="Pratinjau foto bukti yang belum dikirim" data-photo-preview>
<canvas class="hidden" data-photo-canvas></canvas>
<div class="mt-4 flex flex-wrap gap-3"><button type="button" class="button" data-start-camera>Buka kamera / ambil ulang</button><button type="button" class="button" disabled data-capture-photo>Ambil foto</button><button type="button" class="reset-link" data-stop-camera>Hentikan kamera</button></div>
<p class="mt-3 text-sm text-slate-500" role="status" aria-live="polite" data-camera-status>Belum ada foto.</p>
</section>
<section><h2 class="font-semibold text-purple">2. Izinkan lokasi perangkat</h2><p class="mt-2 text-xs leading-5 text-slate-500">Permintaan lokasi dapat membutuhkan beberapa detik. Akurasi bergantung perangkat; koordinat bukan bukti identitas terpercaya.</p><button type="button" class="button mt-4" data-get-location>Ambil lokasi saya</button><p class="mt-4 break-words text-sm text-slate-500" role="status" aria-live="polite" data-location-status>Belum ada koordinat.</p></section>
</div>
<p class="mt-6 text-xs leading-5 text-slate-500">Foto dan lokasi hanya dikirim saat tombol kirim ditekan. Ambil ulang jika lebih dari 15 menit. Jika terjadi error validasi, foto/lokasi tetap di perangkat selama halaman ini terbuka; memuat ulang halaman akan menghapusnya.</p>
<button class="button mt-5" type="submit" disabled data-correction-submit>Kirim untuk ditinjau HR</button>
</form>
</x-layout>

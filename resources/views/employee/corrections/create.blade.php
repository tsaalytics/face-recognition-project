<x-layout title="Ajukan Koreksi">
<div class="request-form-page employee-profile-page">
<header class="profile-page-header"><h1>Ajukan Koreksi Absensi</h1><nav class="list-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('employee.home') }}">Beranda</a><span aria-hidden="true">/</span><a href="{{ route('employee.corrections.index') }}">Koreksi Saya</a><span aria-hidden="true">/</span><span aria-current="page">Ajukan Koreksi Absensi</span></nav></header>
<form method="POST" enctype="multipart/form-data" action="{{ route('employee.corrections.store') }}" class="profile-card request-form-card" data-correction-form>
@csrf
<div class="profile-section-heading"><div><h2>Informasi Permohonan Koreksi</h2><p>Lengkapi data pengajuan. Kolom bertanda <span class="required-mark">*</span> wajib diisi.</p></div></div>
<div role="alert" tabindex="-1" class="mb-5 hidden whitespace-pre-line rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" data-correction-errors></div>
<div class="request-form-grid">
<x-form-field name="type" label="Jenis absensi" :required="true"><select class="field" name="type" id="type" required><option value="check_in" @selected(old('type')==='check_in')>Check In</option><option value="check_out" @selected(old('type')==='check_out')>Check Out</option></select></x-form-field>
<div class="request-form-guidance"></div>
<x-form-field name="reason" label="Alasan koreksi / gagal absensi" :required="true" :wide="true"><textarea class="field" id="reason" name="reason" rows="4" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea><p class="profile-help">Tuliskan alasan pengajuan dalam 10-2000 karakter.</p></x-form-field>
</div>
<noscript><p role="alert" class="mt-5 rounded-xl bg-amber-50 p-4">JavaScript diperlukan untuk kamera dan lokasi. Hubungi HR untuk prosedur alternatif.</p></noscript>
<section class="request-consent-section"><h2 class="font-semibold text-purple">Privasi dan persetujuan</h2><p class="mt-2 text-sm leading-6 text-slate-600">Foto dari kamera, waktu pengambilan, dan koordinat lokasi akan dikirim bersama permohonan untuk ditinjau HR, disimpan secara privat, dan dapat dilihat Anda serta HR. Kamera dan lokasi hanya diminta setelah Anda menekan tombol di bawah. Anda dapat menghentikan kamera dan meninggalkan formulir sebelum mengirim.</p><p class="mt-2 text-sm leading-6 text-slate-600">Jika tidak bersedia atau izin ditolak, permohonan ini tidak dapat dikirim. Hubungi HR untuk peninjauan manual melalui prosedur alternatif perusahaan. Tidak ada lokasi atau foto pengganti yang dibuat otomatis.</p>
<label class="mt-4 flex items-start gap-3 text-sm"><input class="mt-1 accent-magenta" type="checkbox" name="privacy_consent" value="1" required data-privacy-consent><span>Saya memahami penggunaan foto dan lokasi untuk peninjauan permohonan ini.</span></label>
</section>
<div class="profile-enrollment-grid request-capture-grid">
<section><h2 class="font-semibold text-purple">1. Ambil foto langsung</h2>
<video class="mt-4 hidden max-h-80 w-full rounded-xl bg-slate-900 object-contain" autoplay muted playsinline data-camera-video></video>
<img class="mt-4 hidden max-h-80 w-full rounded-xl object-contain" alt="Pratinjau foto bukti yang belum dikirim" data-photo-preview>
<canvas class="hidden" data-photo-canvas></canvas>
<div class="mt-4 flex flex-wrap gap-3"><button type="button" class="button" data-start-camera>Buka kamera / ambil ulang</button><button type="button" class="button" disabled data-capture-photo>Ambil foto</button><button type="button" class="reset-link" data-stop-camera>Hentikan kamera</button></div>
<p class="mt-3 text-sm text-slate-500" role="status" aria-live="polite" data-camera-status>Belum ada foto.</p>
</section>
<section><h2 class="font-semibold text-purple">2. Izinkan lokasi perangkat</h2><p class="mt-2 text-xs leading-5 text-slate-500">Permintaan lokasi dapat membutuhkan beberapa detik. Akurasi bergantung perangkat; koordinat bukan bukti identitas terpercaya.</p><button type="button" class="button mt-4" data-get-location>Ambil lokasi saya</button><p class="mt-4 break-words text-sm text-slate-500" role="status" aria-live="polite" data-location-status>Belum ada koordinat.</p></section>
</div>
<p class="mt-6 text-xs leading-5 text-slate-500">Foto dan lokasi hanya dikirim saat tombol kirim ditekan. Ambil ulang jika lebih dari 15 menit. Jika terjadi error validasi, foto/lokasi tetap di perangkat selama halaman ini terbuka; memuat ulang halaman akan menghapusnya.</p>
<div class="profile-form-actions"><a class="list-control list-secondary" href="{{ route('employee.corrections.index') }}">Batal</a><button class="list-control list-primary" type="submit" disabled data-correction-submit>Kirim untuk ditinjau HR</button></div>
</form>
</div>
</x-layout>

<x-layout title="Face Enrollment">
<a class="reset-link" href="{{ route('hr.enrollment') }}">&larr; Daftar enrollment</a>
<h1 class="mt-4 text-3xl font-bold text-purple">{{ $sampleCount ? 'Re-enroll Face' : 'Register Face' }}</h1>
<p class="mt-2 font-semibold">{{ $employee->name }} / ID Karyawan: {{ $employee->business_id ?? 'Belum diterbitkan' }}</p>
<p class="mt-2 text-sm text-slate-600">Status: {{ $sampleCount ? 'Enrolled' : 'Not Enrolled' }} · {{ $sampleCount }} embedding tersimpan.</p>
<section class="mt-6 rounded-2xl border border-purple/10 bg-white p-6">
<h2 class="font-semibold text-purple">Persetujuan biometrik untuk demo nonkomersial</h2>
<p class="mt-3 text-sm leading-6">HR harus memperoleh persetujuan karyawan sebelum membuka kamera. Ambil 3–5 sampel dengan variasi posisi ringan, satu orang saja. Foto hanya diproses sementara; embedding terenkripsi disimpan sampai dihapus atau diganti. Embedding tetap data sensitif. Re-enroll menghapus embedding lama setelah seluruh sampel baru berhasil. Persetujuan dapat ditarik melalui HR dengan Delete Enrollment.</p>
<p class="mt-3 text-sm leading-6">Belum ada liveness atau jaminan anti-spoofing. Fitur ini belum mencatat absensi. Untuk menolak biometrik, gunakan workflow koreksi/izin yang sudah tersedia dan hubungi HR.</p>
@if($employee->is_active && $employee->business_id)
<form method="POST" class="mt-5" data-face-enrollment data-version="{{ $employee->face_enrollment_version }}" action="{{ route('hr.faces.store', $employee) }}">
@csrf
<label class="flex items-start gap-3 text-sm"><input type="checkbox" data-face-consent class="mt-1 accent-magenta"> Saya sebagai HR menyatakan karyawan ini sudah menerima penjelasan dan menyetujui pengambilan serta penyimpanan embedding untuk demo ini (biometric-demo-v1).</label>
<div class="relative mt-5 max-w-xl overflow-hidden rounded-2xl bg-slate-900"><video data-face-video autoplay muted playsinline class="aspect-video w-full object-contain" aria-label="Preview kamera enrollment"></video><div class="pointer-events-none absolute inset-x-[30%] inset-y-[12%] rounded-full border-2 border-white/70" aria-hidden="true"></div></div>
<canvas data-face-canvas class="hidden"></canvas>
<p data-face-status role="status" class="mt-3 text-sm">Belum ada sampel. Kamera belum aktif.</p>
<div data-face-error role="alert" tabindex="-1" class="hidden mt-3 rounded-xl bg-rose-50 p-4 text-sm text-rose-800"></div>
<div class="mt-4 flex flex-wrap gap-3"><button type="button" class="button" data-face-start>Buka kamera</button><button type="button" class="button" data-face-capture disabled>Ambil sampel</button><button type="button" class="button" data-face-clear>Ulangi sampel</button><button type="submit" class="button" data-face-submit disabled>Simpan enrollment</button></div>
<noscript><p>Aktifkan JavaScript untuk menggunakan kamera. Tidak ada upload foto pengganti.</p></noscript>
</form>
@else<p class="mt-4 text-sm text-rose-700">Profil harus aktif dan memiliki ID Karyawan sebelum enrollment.</p>@endif
</section>
@if($sampleCount)
<form method="POST" action="{{ route('hr.faces.destroy', $employee) }}" class="mt-6 rounded-2xl border border-rose-200 bg-white p-6" data-submit-form>
@csrf @method('DELETE')<input type="hidden" name="enrollment_version" value="{{ $employee->face_enrollment_version }}">
<label class="flex items-start gap-3 text-sm"><input type="checkbox" name="confirmation" value="1" required class="mt-1"> Hapus seluruh embedding aktif karyawan ini. Audit metadata tetap disimpan; pemulihan memerlukan enrollment baru.</label>
<button class="button mt-4" data-submit-button>Delete Enrollment</button>
</form>
@endif
<h2 class="mt-8 text-xl font-bold text-purple">Audit perubahan enrollment</h2>
<div class="table-panel mt-4" role="region" aria-label="Audit enrollment" tabindex="0"><table class="data-table"><thead><tr><th scope="col">Waktu server</th><th scope="col">Aksi</th><th scope="col">Aktor HR</th><th scope="col">Versi / sampel</th></tr></thead><tbody>
@forelse($audits as $audit)<tr><td>{{ $audit->occurred_at->format('d-m-Y H:i:s') }} WIB</td><td>{{ $audit->action }}</td><td>#{{ $audit->actor_id }}</td><td>{{ $audit->version }} / {{ $audit->sample_count }} input, {{ $audit->stored_count }} tersimpan</td></tr>@empty<tr><td colspan="4" class="empty-state">Belum ada perubahan enrollment.</td></tr>@endforelse
</tbody></table></div>{{ $audits->links() }}
</x-layout>

<x-layout title="Ajukan Izin">
<a class="reset-link" href="{{ route('employee.leaves.index') }}">&larr; Kembali ke izin saya</a>
<h1 class="mt-4 text-3xl font-bold text-purple">Ajukan izin, sakit, atau cuti</h1>
<p class="mt-2 text-sm leading-6 text-slate-500">Pengajuan akan ditinjau HR. Dokumen pendukung bersifat privat dan opsional.</p>
<form method="POST" enctype="multipart/form-data" action="{{ route('employee.leaves.store') }}" class="mt-6 max-w-3xl rounded-2xl bg-white p-6 md:p-8" data-submit-form>
@csrf
<div><label class="filter-label" for="type">Jenis izin</label><select class="field" name="type" id="type" required>@foreach(['permission'=>'Izin','sick'=>'Sakit','annual'=>'Cuti'] as $value=>$label)<option value="{{ $value }}" @selected(old('type')===$value)>{{ $label }}</option>@endforeach</select></div>
<div class="mt-5 grid gap-5 sm:grid-cols-2"><div><label class="filter-label" for="start_date">Tanggal mulai</label><input class="field" type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" required></div><div><label class="filter-label" for="end_date">Tanggal selesai</label><input class="field" type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" required></div></div>
<div class="mt-5"><label class="filter-label" for="reason">Alasan (10-2000 karakter)</label><textarea class="field" id="reason" name="reason" rows="4" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></div>
<div class="mt-5"><label class="filter-label" for="attachment">Dokumen pendukung (opsional)</label><input class="field" type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"><p class="mt-2 text-xs text-slate-500">PDF/JPEG/PNG/WebP, maksimal 5 MB. Hanya unggah dokumen yang diperlukan untuk peninjauan HR.</p></div>
<p class="mt-5 text-xs leading-5 text-slate-500">Maksimal 366 hari, termasuk akhir pekan. Tanggal tidak boleh tumpang tindih dengan izin pending/approved atau catatan hadir. Belum ada perhitungan saldo cuti. Berkas hanya dapat diakses Anda dan HR.</p>
<button class="button mt-6" data-submit-button>Kirim pengajuan izin</button>
</form>
</x-layout>

<x-layout title="Ajukan Izin">
<div class="request-form-page employee-profile-page">
<header class="profile-page-header"><h1>Ajukan Izin</h1><nav class="list-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('employee.home') }}">Beranda</a><span aria-hidden="true">/</span><a href="{{ route('employee.leaves.index') }}">Izin Saya</a><span aria-hidden="true">/</span><span aria-current="page">Ajukan Izin</span></nav></header>
<form method="POST" enctype="multipart/form-data" action="{{ route('employee.leaves.store') }}" class="profile-card request-form-card" data-submit-form>
@csrf
<div class="profile-section-heading"><div><h2>Informasi Pengajuan Izin</h2><p>Lengkapi data pengajuan. Kolom bertanda <span class="required-mark">*</span> wajib diisi.</p></div></div>
<div class="request-form-grid">
<x-form-field name="type" label="Jenis izin" :required="true"><select class="field" name="type" id="type" required>@foreach(['permission'=>'Izin','sick'=>'Sakit','annual'=>'Cuti'] as $value=>$label)<option value="{{ $value }}" @selected(old('type')===$value)>{{ $label }}</option>@endforeach</select></x-form-field>
<div class="request-form-guidance"></div>
<x-form-field name="start_date" label="Tanggal mulai" :required="true"><input class="field" type="date" name="start_date" id="start_date" value="{{ old('start_date') }}" required></x-form-field>
<x-form-field name="end_date" label="Tanggal selesai" :required="true"><input class="field" type="date" name="end_date" id="end_date" value="{{ old('end_date') }}" required></x-form-field>
<x-form-field name="reason" label="Alasan pengajuan" :required="true" :wide="true"><textarea class="field" id="reason" name="reason" rows="4" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea><p class="profile-help">Tuliskan alasan pengajuan dalam 10-2000 karakter.</p></x-form-field>
<x-form-field name="attachment" label="Dokumen pendukung (opsional)" :wide="true"><input class="field" type="file" name="attachment" id="attachment" accept=".pdf,.jpg,.jpeg,.png,.webp"><p class="profile-help">PDF/JPEG/PNG/WebP, maksimal 5 MB.</p></x-form-field>
</div>
<div class="profile-form-actions"><a class="list-control list-secondary" href="{{ route('employee.leaves.index') }}">Batal</a><button class="list-control list-primary" data-submit-button>Kirim pengajuan izin</button></div>
</form>
</div>
</x-layout>

@php
    $isCorrection = $kind === 'corrections';
    $listLabel = $isCorrection ? ($audience === 'hr' ? 'Approval Koreksi' : 'Koreksi Saya') : ($audience === 'hr' ? 'Pengajuan Izin' : 'Izin Saya');
    $statusLabel = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'][$request->status] ?? $request->status;
    $hasEvidence = $isCorrection || $request->attachment_path;
@endphp
<div class="request-detail">
<nav class="request-breadcrumb" aria-label="Breadcrumb">
<a href="{{ route($audience.'.home') }}">{{ $audience === 'hr' ? 'Dashboard' : 'Beranda' }}</a><span aria-hidden="true">/</span>
<a href="{{ route($audience.'.'.$kind.'.index') }}">{{ $listLabel }}</a><span aria-hidden="true">/</span><span aria-current="page">Detail Permohonan</span>
</nav>
<div class="request-title"><h1>{{ $isCorrection ? 'Koreksi Absensi' : 'Pengajuan Izin' }} #{{ $request->id }}</h1><span class="request-status request-status-{{ $request->status }}"><span aria-hidden="true">@if($request->status === 'approved')&#10003;@elseif($request->status === 'rejected')&times;@else&#9671;@endif</span>{{ $statusLabel }}</span></div>
<section class="request-card request-information" aria-labelledby="request-information-title">
<div class="request-employee">
<svg class="request-avatar" viewBox="0 0 100 100" aria-hidden="true"><defs><clipPath id="request-avatar-clip"><circle cx="50" cy="50" r="50"/></clipPath></defs><g clip-path="url(#request-avatar-clip)"><circle cx="50" cy="50" r="50" fill="#eee3f8"/><circle cx="50" cy="35" r="18" fill="#b5a3c6"/><ellipse cx="50" cy="94" rx="38" ry="37" fill="#b5a3c6"/></g></svg>
<h2>{{ $request->employee->name }}</h2><p>{{ $request->employee->business_id ?? 'Belum diterbitkan' }}</p><p class="request-employee-meta">{{ $request->employee->employee_number }} &middot; {{ $request->employee->division->name }}</p>
</div>
<div class="request-information-body"><h2 id="request-information-title">Informasi Permohonan {{ $isCorrection ? 'Koreksi' : 'Izin' }}</h2>
<div class="request-information-columns"><dl class="request-fields">
<div><dt>Jenis</dt><dd>{{ ['check_in'=>'Check In','check_out'=>'Check Out','permission'=>'Izin','sick'=>'Sakit','annual'=>'Cuti'][$request->type] ?? $request->type }}</dd></div>
@if($isCorrection)
<div><dt>Tanggal absensi koreksi</dt><dd>{{ $request->attendance_date->format('d M Y') }}</dd></div>
<div><dt>Waktu absensi koreksi / Sesi</dt><dd>{{ $request->submitted_at->format('d M Y H:i:s') }} / {{ $request->session }}</dd></div>
<div><dt>Waktu pengambilan (perangkat)</dt><dd>{{ $request->captured_at->format('d M Y H:i:s') }}</dd></div>
@else
<div><dt>Tanggal izin</dt><dd>{{ $request->start_date->format('d M Y') }} &ndash; {{ $request->end_date->format('d M Y') }}</dd></div>
@endif
<div><dt>Diajukan (server)</dt><dd>{{ $request->submitted_at->format('d M Y H:i:s') }} / Asia/Jakarta</dd></div>
@if($isCorrection)
<div><dt>Koordinat</dt><dd>{{ $request->latitude }}, {{ $request->longitude }}
@if($request->is_demo)<span class="request-demo-note">Fixture sintetis, bukan lokasi perangkat.</span>@else<a class="reset-link block" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q={{ $request->latitude }},{{ $request->longitude }}">Lihat peta</a>@endif
</dd></div>
@endif
</dl><dl class="request-fields request-reason"><div><dt>Alasan</dt><dd class="whitespace-pre-wrap">{{ $request->reason }}</dd></div><div><dt>Status</dt><dd class="font-semibold">{{ $statusLabel }}</dd></div></dl></div></div>
</section>
<section class="request-card" aria-labelledby="request-evidence-title"><h2 id="request-evidence-title">Bukti Pendukung</h2>
@if($hasEvidence)
<div class="request-evidence"><div class="request-file"><x-icon name="document"/><div><h3>{{ $isCorrection ? 'Bukti Permohonan' : 'Dokumen Perizinan' }}</h3><p>{{ $isCorrection ? ($request->is_demo ? 'Berkas teks (demo)' : 'Foto bukti koreksi absensi') : 'Lampiran permohonan izin' }}</p></div><a class="request-download" href="{{ route($audience.'.'.($isCorrection ? 'corrections.evidence' : 'leaves.attachment'), $request) }}"><x-icon name="download"/>{{ $isCorrection && !$request->is_demo ? 'Buka bukti privat' : 'Unduh' }}</a></div>
@if($isCorrection && !$request->is_demo)<img class="request-evidence-image" data-evidence-image src="{{ route($audience.'.corrections.evidence', $request) }}" alt="Foto bukti koreksi absensi"><p class="mt-3 hidden text-sm text-amber-800" data-evidence-error role="status">Pratinjau bukti tidak tersedia. Coba tautan bukti privat di atas atau hubungi HR.</p>@endif
@else<p class="request-empty">Tidak ada dokumen pendukung.</p>@endif
</section>
@if($audience === 'hr')
@can('approve',$request)
<form method="POST" action="{{ route('hr.'.$kind.'.decision',$request) }}" class="request-card request-decision" data-submit-form data-decision-form>
@csrf
<h2 class="font-semibold text-purple">Keputusan HR</h2>
<p class="mt-2 text-sm text-slate-500">Keputusan bersifat final pada fase ini. Persetujuan akan memperbarui kehadiran dan mencatat riwayat. Periksa detail sebelum menyimpan.</p>
<div class="mt-5"><label class="filter-label" for="decision">Keputusan</label><select id="decision" name="decision" required class="field"><option value="">Pilih keputusan</option><option value="approved" @selected(old('decision')==='approved')>Approve</option><option value="rejected" @selected(old('decision')==='rejected')>Reject</option></select></div>
<div class="mt-4"><label class="filter-label" for="notes">Catatan keputusan (wajib untuk Reject)</label><textarea class="field" id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea></div>
<button class="button mt-5" data-submit-button>Simpan keputusan</button>
</form>
@endcan
@endif

</div>


<x-layout title="Detail Permohonan">
<a class="reset-link" href="{{ route('hr.'.$kind.'.index') }}">&larr; Kembali ke daftar</a>
<div class="mt-4 flex flex-wrap items-center justify-between gap-3"><h1 class="text-3xl font-bold text-purple">{{ $kind==='corrections' ? 'Koreksi absensi' : 'Pengajuan izin' }} #{{ $request->id }}</h1><x-status :value="$request->status"/></div>
<div class="mt-6 grid gap-6 xl:grid-cols-2">
<section class="rounded-2xl bg-white p-6"><h2 class="text-lg font-semibold text-purple">{{ $request->employee->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $request->employee->employee_number }} / ID: {{ $request->employee->business_id ?? 'Belum diterbitkan' }} / {{ $request->employee->division->name }}</p>
<dl class="mt-6 space-y-4 text-sm">
<div><dt class="text-slate-500">Jenis</dt><dd class="mt-1">{{ ['check_in'=>'Check In','check_out'=>'Check Out','permission'=>'Izin','sick'=>'Sakit','annual'=>'Cuti'][$request->type] ?? $request->type }}</dd></div>
@if($kind==='corrections')
<div><dt class="text-slate-500">Waktu absensi koreksi / Sesi</dt><dd class="mt-1">{{ $request->submitted_at->format('d M Y H:i:s') }} / {{ $request->session }} / Asia/Jakarta</dd></div>
<div><dt class="text-slate-500">Waktu pengambilan (perangkat)</dt><dd class="mt-1">{{ $request->captured_at->format('d M Y H:i:s') }}</dd></div>
<div><dt class="text-slate-500">Koordinat</dt><dd class="mt-1">{{ $request->latitude }}, {{ $request->longitude }}
@if(!$request->is_demo)<a class="ml-2 reset-link" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps?q={{ $request->latitude }},{{ $request->longitude }}">Lihat peta</a>@else<span class="block text-xs text-magenta">Fixture sintetis, bukan lokasi perangkat.</span>@endif
</dd></div>
@else
<div><dt class="text-slate-500">Tanggal izin</dt><dd class="mt-1">{{ $request->start_date->format('d M Y') }} - {{ $request->end_date->format('d M Y') }}</dd></div>
@endif
<div><dt class="text-slate-500">Diajukan (server)</dt><dd class="mt-1">{{ $request->submitted_at->format('d M Y H:i:s') }} / Asia/Jakarta</dd></div>
<div><dt class="text-slate-500">Alasan</dt><dd class="mt-1 whitespace-pre-wrap break-words">{{ $request->reason }}</dd></div>
</dl>
</section>
<section class="rounded-2xl bg-white p-6"><h2 class="font-semibold text-purple">Bukti pendukung</h2>
@if($kind==='corrections')
@if($request->is_demo)<p class="mt-4 rounded-xl bg-lavender p-4 text-sm text-purple">DATA DEMO SINTETIS: bukti berupa berkas teks, tanpa foto wajah atau GPS nyata.</p>
@else<img class="mt-4 max-h-96 max-w-full rounded-xl object-contain" data-evidence-image src="{{ route('hr.corrections.evidence',$request) }}" alt="Foto bukti koreksi absensi"><p class="mt-3 hidden text-sm text-amber-800" data-evidence-error role="status">Pratinjau bukti tidak tersedia. Coba tautan bukti privat di bawah atau hubungi HR.</p>@endif
<a class="mt-4 inline-block reset-link" href="{{ route('hr.corrections.evidence',$request) }}">Buka bukti privat</a>
<p class="mt-4 text-xs leading-5 text-slate-500">Foto, lokasi, dan waktu perangkat bukan bukti identitas yang sepenuhnya terpercaya. HR wajib menilai konteks dan dapat menghubungi karyawan. Akses bukti dibatasi.</p>
@elseif($request->attachment_path)<a class="mt-4 inline-block reset-link" href="{{ route('hr.leaves.attachment',$request) }}">Download dokumen privat</a>
@else<p class="mt-4 text-sm text-slate-500">Tidak ada dokumen pendukung.</p>@endif
</section>
</div>
@can('approve',$request)
<form method="POST" action="{{ route('hr.'.$kind.'.decision',$request) }}" class="mt-6 rounded-2xl bg-white p-6" data-submit-form data-decision-form>
@csrf
<h2 class="font-semibold text-purple">Keputusan HR</h2>
<p class="mt-2 text-sm text-slate-500">Keputusan bersifat final pada fase ini. Persetujuan akan memperbarui kehadiran dan mencatat riwayat. Periksa detail sebelum menyimpan.</p>
<div class="mt-5"><label class="filter-label" for="decision">Keputusan</label><select id="decision" name="decision" required class="field"><option value="">Pilih keputusan</option><option value="approved" @selected(old('decision')==='approved')>Approve</option><option value="rejected" @selected(old('decision')==='rejected')>Reject</option></select></div>
<div class="mt-4"><label class="filter-label" for="notes">Catatan keputusan (wajib untuk Reject)</label><textarea class="field" id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea></div>
<button class="button mt-5" data-submit-button>Simpan keputusan</button>
</form>
@endcan
<section class="mt-6 rounded-2xl bg-white p-6"><h2 class="font-semibold text-purple">Riwayat keputusan</h2>
@forelse($request->approvalLogs as $log)<div class="mt-4 border-t border-slate-100 pt-4 text-sm"><p>{{ $log->reviewer->name }} / {{ $log->reviewed_at->format('d M Y H:i:s') }}</p><p class="mt-2">{{ $log->from_status }} &rarr; {{ $log->to_status }}</p><p class="mt-2 whitespace-pre-wrap break-words text-slate-500">{{ $log->notes ?? 'Tanpa catatan tambahan.' }}</p></div>
@empty<p class="mt-4 text-sm text-slate-500">Belum ada keputusan.</p>@endforelse
</section>
</x-layout>

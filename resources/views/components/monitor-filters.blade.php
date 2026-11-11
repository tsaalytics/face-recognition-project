@props(['divisions','attendance'=>false,'reset'])
<form method="GET" class="filter-panel" aria-label="Filter data">
<div class="grow"><label for="q" class="filter-label">Nama / NIK</label><input class="field" id="q" name="q" value="{{ request('q') }}" placeholder="Cari karyawan..."></div>
<div><label for="division_id" class="filter-label">Divisi</label><select class="field" id="division_id" name="division_id"><option value="">Semua divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string)request('division_id')===(string)$division->id)>{{ $division->name }}</option>@endforeach</select></div>
@if($attendance)
<div><label for="from" class="filter-label">Dari tanggal</label><input class="field" id="from" name="from" type="date" value="{{ request('from') }}"></div>
<div><label for="to" class="filter-label">Sampai tanggal</label><input class="field" id="to" name="to" type="date" value="{{ request('to') }}"></div>
<div><label for="status" class="filter-label">Status</label><select class="field" id="status" name="status"><option value="">Semua status</option>@foreach(['present'=>'Hadir','late'=>'Terlambat','leave'=>'Izin','absent'=>'Tidak hadir'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></div>
@else
<div><label for="active" class="filter-label">Status</label><select class="field" id="active" name="active"><option value="">Semua status</option><option value="1" @selected(request('active')==='1')>Aktif</option><option value="0" @selected(request('active')==='0')>Nonaktif</option></select></div>
@endif
<button class="button">Terapkan</button><a href="{{ $reset }}" class="reset-link">Reset</a>
</form>

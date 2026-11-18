@props(['divisions', 'attendance' => false])
<div><label for="q">Nama / NIK</label><input class="field" id="q" name="q" value="{{ request('q') }}" placeholder="Cari karyawan…"></div>
<div><label for="division_id">Divisi</label><select class="field" id="division_id" name="division_id"><option value="">Semua divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string) request('division_id') === (string) $division->id)>{{ $division->name }}</option>@endforeach</select></div>
@if($attendance)
<div class="list-filter-date-range"><div><label for="from">Dari tanggal</label><input class="field" id="from" name="from" type="date" value="{{ request('from') }}"></div><div><label for="to">Sampai tanggal</label><input class="field" id="to" name="to" type="date" value="{{ request('to') }}"></div></div>
<div><label for="status">Status kehadiran</label><select class="field" id="status" name="status"><option value="">Semua status</option>@foreach(['present'=>'Hadir', 'late'=>'Terlambat', 'leave'=>'Izin', 'absent'=>'Tidak hadir'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
@else
<div><label for="active">Status karyawan</label><select class="field" id="active" name="active"><option value="">Semua status</option><option value="1" @selected(request('active') === '1')>Aktif</option><option value="0" @selected(request('active') === '0')>Nonaktif</option></select></div>
@endif

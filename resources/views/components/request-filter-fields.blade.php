@props(['search' => false])
@if($search)<div><label for="q">Nama / NIK</label><input class="field" id="q" name="q" value="{{ request('q') }}" placeholder="Cari karyawan…"></div>@endif
<div><label for="status">Status</label><select class="field" id="status" name="status"><option value="">Semua status</option>@foreach(['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>

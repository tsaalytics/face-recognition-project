<x-layout title="Direktori Karyawan">
@php
    $filterKeys = ['q', 'division_id', 'active'];
    $activeFilters = collect(request()->only($filterKeys))->filter(fn ($value) => is_scalar($value) && (string) $value !== '')->count();
@endphp
<div class="compact-list">
<x-list-header title="Direktori Karyawan" breadcrumb="Karyawan" :dashboard="route('hr.home')">
    <x-per-page :paginator="$employees" :action="route('hr.employees.index')" :filters="$filterKeys"/>
    <x-list-filter :active="$activeFilters" :action="route('hr.employees.index')" :reset="route('hr.employees.index', ['per_page' => $employees->perPage()])" :per-page="$employees->perPage()">
        <div><label for="q">Nama / NIK</label><input id="q" name="q" class="field" value="{{ request('q') }}" placeholder="Cari karyawan…"></div>
        <div><label for="division_id">Divisi</label><select id="division_id" name="division_id" class="field"><option value="">Semua divisi</option>@foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string) request('division_id') === (string) $division->id)>{{ $division->name }}</option>@endforeach</select></div>
        <div><label for="active">Status karyawan</label><select id="active" name="active" class="field"><option value="">Semua status</option><option value="1" @selected(request('active') === '1')>Aktif</option><option value="0" @selected(request('active') === '0')>Nonaktif</option></select></div>
    </x-list-filter>
    @can('create', \App\Models\Employee::class)
    <a href="{{ route('hr.employees.create') }}" class="list-control list-primary"><x-icon name="plus"/>Tambah Karyawan</a>
    @endcan
</x-list-header>
<div class="list-secondary-bar">

</div>
<x-employee-table :employees="$employees" role="hr"/>
<x-list-pagination :paginator="$employees"/>
</div>
</x-layout>

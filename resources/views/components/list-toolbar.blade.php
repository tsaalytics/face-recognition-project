@props(['title', 'breadcrumb', 'dashboard', 'action', 'paginator', 'filters' => [], 'description' => '', 'unit' => 'data'])
@php
    $activeFilters = collect(request()->only($filters))->filter(fn ($value) => is_scalar($value) && (string) $value !== '')->count();
@endphp
<x-list-header :title="$title" :breadcrumb="$breadcrumb" :dashboard="$dashboard">
    <x-per-page :paginator="$paginator" :action="$action" :filters="$filters"/>
    @if(count($filters))
    <x-list-filter :active="$activeFilters" :action="$action" :reset="$action.'?'.http_build_query(['per_page' => $paginator->perPage()])" :per-page="$paginator->perPage()">{{ $slot }}</x-list-filter>
    @endif
    {{ $actions ?? '' }}
</x-list-header>
<div class="list-secondary-bar">
    @if($activeFilters)<span class="list-active-indicator">{{ $activeFilters }} filter aktif</span>@endif
    @if($description)<span class="list-readonly-note">{{ $description }}</span>@endif
</div>

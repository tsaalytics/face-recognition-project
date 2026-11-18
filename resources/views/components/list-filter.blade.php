@props(['active' => 0, 'reset', 'action', 'perPage' => 10])
<details class="list-filter" data-list-filter>
    <summary class="list-control"><x-icon name="filter"/>Filter @if($active)<span class="list-filter-count" aria-label="{{ $active }} filter aktif">{{ $active }}</span>@endif</summary>
    <div class="list-filter-popover">
        <div class="list-filter-heading"><h2>Filter data</h2><span>{{ $active ? $active.' filter aktif' : 'Sesuaikan pencarian' }}</span></div>
        <form method="GET" action="{{ $action }}" aria-label="Filter data">
            <input type="hidden" name="per_page" value="{{ $perPage }}">
            <div class="list-filter-fields">{{ $slot }}</div>
            <div class="list-filter-actions"><a href="{{ $reset }}" class="list-control">Reset Filter</a><button type="submit" class="list-control list-primary">Apply Filter</button></div>
        </form>
    </div>
</details>

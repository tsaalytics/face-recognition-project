@props(['paginator', 'action', 'filters' => []])
<form method="GET" action="{{ $action }}" class="list-per-page" data-per-page-form>
    @foreach(request()->only($filters) as $key => $value)
        @if(is_scalar($value) && (string) $value !== '')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
    @endforeach
    <label for="per_page">Showing</label>
    <select id="per_page" name="per_page" aria-label="Jumlah baris per halaman" data-per-page-select>
        @foreach([5, 10, 25, 50] as $count)<option value="{{ $count }}" @selected($paginator->perPage() === $count)>{{ $count }}</option>@endforeach
    </select>
    <button type="submit" class="list-control" data-per-page-submit>Tampilkan</button>
</form>

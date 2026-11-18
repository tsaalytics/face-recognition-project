<nav class="ft-pagination" role="navigation" aria-label="Navigasi halaman">
    @if($paginator->onFirstPage())
        <span class="ft-pagination-previous ft-pagination-disabled" aria-disabled="true"><span aria-hidden="true">‹</span><span>Sebelumnya</span></span>
    @else
        <a class="ft-pagination-previous" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya"><span aria-hidden="true">‹</span><span>Sebelumnya</span></a>
    @endif

    <div class="ft-pagination-pages ft-pagination-desktop">
        @foreach($elements as $element)
            @if(is_string($element))
                <span class="ft-pagination-ellipsis" aria-hidden="true">...</span>
            @else
                @foreach($element as $page => $url)
                    @if($page === $paginator->currentPage())
                        <span class="ft-pagination-page ft-pagination-current" aria-current="page" aria-label="Halaman {{ $page }}, halaman saat ini">{{ $page }}</span>
                    @else
                        <a class="ft-pagination-page" href="{{ $url }}" aria-label="Ke halaman {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
    </div>

    @php
        $mobilePages = collect([1, $paginator->currentPage(), $paginator->lastPage()])->unique()->sort()->values();
        $previousMobilePage = null;
    @endphp
    <div class="ft-pagination-pages ft-pagination-mobile">
        @foreach($mobilePages as $page)
            @if($previousMobilePage !== null && $page - $previousMobilePage > 1)<span class="ft-pagination-ellipsis" aria-hidden="true">...</span>@endif
            @if($page === $paginator->currentPage())
                <span class="ft-pagination-page ft-pagination-current" aria-current="page" aria-label="Halaman {{ $page }}, halaman saat ini">{{ $page }}</span>
            @else
                <a class="ft-pagination-page" href="{{ $paginator->url($page) }}" aria-label="Ke halaman {{ $page }}">{{ $page }}</a>
            @endif
            @php
                $previousMobilePage = $page;
            @endphp
        @endforeach
    </div>

    @if($paginator->hasMorePages())
        <a class="ft-pagination-next" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman selanjutnya"><span>Selanjutnya</span><span aria-hidden="true">›</span></a>
    @else
        <span class="ft-pagination-next ft-pagination-disabled" aria-disabled="true"><span>Selanjutnya</span><span aria-hidden="true">›</span></span>
    @endif
</nav>

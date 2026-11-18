@props(['title', 'breadcrumb', 'dashboard', 'parents' => []])
<header class="list-header">
    <div class="list-heading">
        <h1>{{ $title }}</h1>
        <nav aria-label="Breadcrumb" class="list-breadcrumb">
            <a href="{{ $dashboard }}">Dashboard</a><span aria-hidden="true">/</span>
            @foreach($parents as $label => $url)<a href="{{ $url }}">{{ $label }}</a><span aria-hidden="true">/</span>@endforeach
            <span aria-current="page">{{ $breadcrumb }}</span>
        </nav>
    </div>
    <div class="list-actions">{{ $slot }}</div>
</header>

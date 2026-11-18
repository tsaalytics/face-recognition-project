@props(['href', 'label', 'icon'])
<a href="{{ $href }}" class="table-action" title="{{ $label }}" aria-label="{{ $label }}"><x-icon :name="$icon"/><span class="sr-only">{{ $label }}</span></a>

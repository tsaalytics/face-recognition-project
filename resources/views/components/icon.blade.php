@props(['name' => 'home'])
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
@switch($name)
@case('eye')<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>@break
@case('pencil')<path d="m15 5 4 4M4 20l4-1L20 7a2.8 2.8 0 0 0-4-4L4 15z"/>@break
@case('download')<path d="M12 3v12m-5-5 5 5 5-5M4 16v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>@break
@case('filter')<path d="M3 4h18l-7 8v7l-4 2v-9z"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('users')<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3a4 4 0 0 1 0 8"/><circle cx="9" cy="7" r="4"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18m-13 5 3 3 5-5"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>@break
@case('close')<path d="m6 6 12 12M6 18 18 6"/>@break
@case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
@case('menu')<path d="M3 6h18M3 12h18M3 18h18"/>@break
@case('logout')<path d="M12 3v9m-5-7a9 9 0 1 0 10 0"/>@break
@case('document')<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h4"/>@break
@default<path d="m3 10 9-7 9 7M5 9v12h5v-7h4v7h5V9"/>
@endswitch
</svg>

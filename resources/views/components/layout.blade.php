@props(['title' => 'FaceTrack AI'])
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><title>{{ $title }} / FaceTrack AI</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="workspace-page text-slate-800 antialiased">
<a href="#main-content" class="skip-link">Langsung ke konten utama</a>
<div class="workspace-shell" data-workspace>
<aside class="workspace-sidebar" aria-label="Workspace" id="workspace-navigation">
<a href="{{ url('/') }}" class="workspace-brand"><img src="{{ asset('images/facetrack-ai-logo.png') }}" alt="FaceTrack AI" width="1051" height="249"></a>
<div class="workspace-navigation">
@auth

@php
$navigation = auth()->user()->role === \App\Enums\Role::Admin
 ? ['admin.home'=>'Dashboard','admin.users.index'=>'Akun pengguna','admin.employees.index'=>'Karyawan','admin.attendance.index'=>'Riwayat absensi']
 : (auth()->user()->role === \App\Enums\Role::Hr
 ? ['hr.home'=>'Dashboard','hr.employees.index'=>'Karyawan','hr.attendance.index'=>'Riwayat absensi','hr.kiosk-devices.index'=>'Perangkat Kiosk','hr.corrections.index'=>'Approval koreksi','hr.leaves.index'=>'Pengajuan izin']
 : ['employee.home'=>'Beranda','employee.attendance.index'=>'Absensi saya','employee.corrections.index'=>'Koreksi saya','employee.leaves.index'=>'Izin saya']);
@endphp
<nav aria-label="Navigasi utama">
@foreach($navigation as $route=>$label)
@php($active = str_ends_with($route,'.index') ? request()->routeIs(str_replace('.index','.*',$route)) : request()->routeIs($route))
@php($icon = str_contains($route, 'home') ? 'home' : (str_contains($route, 'employees') || str_contains($route, 'users') ? 'users' : (str_contains($route, 'attendance') ? 'calendar' : 'document')))
<a href="{{ route($route) }}" @if($active) aria-current="page" @endif class="workspace-nav-link"><x-icon :name="$icon"/><span>{{ $label }}</span></a>
@endforeach
</nav>
@else
<p class="text-sm leading-6 text-purple-100">Fondasi pengelolaan kehadiran yang tertata, dengan akses sesuai peran.</p>
@endauth
</div>
@auth
<form method="POST" action="{{ route('logout') }}" class="workspace-logout">@csrf<button type="submit"><x-icon name="logout"/><span>Log Out</span></button></form>
@endauth
</aside>
<div class="workspace-content">
<header class="workspace-header">
<div class="workspace-heading"><button type="button" class="workspace-toggle" aria-label="Buka atau tutup sidebar" aria-controls="workspace-navigation" aria-expanded="true" data-sidebar-toggle><x-icon name="menu"/></button><span class="font-semibold">{{ str_starts_with($title, 'Dashboard') ? 'Dashboard' : $title }}</span></div>
@auth
<div class="workspace-profile" title="{{ auth()->user()->name }}"><span class="sr-only">{{ auth()->user()->name }}</span><svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="20" fill="#aaa"/><circle cx="20" cy="15" r="7" fill="white"/><path d="M6 36c1-13 27-13 28 0" fill="white"/></svg></div>
@else<span class="rounded-full bg-lavender px-3 py-1 text-xs font-semibold text-purple">Portal internal</span>@endauth
</header>
<main id="main-content" tabindex="-1" class="workspace-main">
@if(session('warning'))<div role="alert" class="mb-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">{{ session('warning') }}</div>@endif
@if(session('success'))<div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif
@if($errors->any() && !request()->routeIs('login'))
<div role="alert" tabindex="-1" data-validation-summary class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p class="font-semibold">Periksa kembali input Anda.</p><ul class="mt-2 list-inside list-disc">@foreach($errors->messages() as $field=>$messages) @foreach($messages as $error)<li data-error-field="{{ $field }}">{{ $error }}</li>@endforeach @endforeach</ul></div>
@endif
{{ $slot }}</main>
</div>
</div>
</body></html>

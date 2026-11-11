@props(['title' => 'FaceTrack AI'])
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="light"><title>{{ $title }} / FaceTrack AI</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="bg-lavender text-slate-800 antialiased">
<div class="min-h-screen md:flex">
<aside class="bg-purple text-white p-6 md:w-64 md:shrink-0">
<a href="{{ url('/') }}" class="text-2xl font-bold tracking-tight">FaceTrack<span class="text-pink-300"> AI</span></a>
<p class="mt-2 text-xs text-purple-200">EMPLOYEE ATTENDANCE</p>
@auth
<button type="button" class="mt-4 rounded-lg border border-white/30 px-3 py-2 text-sm md:hidden" aria-controls="workspace-navigation" aria-expanded="false" data-nav-toggle>Menu navigasi</button>
@endauth
<div id="workspace-navigation" @class(['mt-8 border-t border-white/20 pt-6','hidden md:block'=>auth()->check()])>
@auth
<p class="text-xs uppercase tracking-widest text-purple-200">Workspace</p>

@php
$navigation = auth()->user()->role === \App\Enums\Role::Admin
 ? ['admin.home'=>'Dashboard','admin.users.index'=>'Akun pengguna','admin.employees.index'=>'Karyawan','admin.attendance.index'=>'Riwayat absensi']
 : (auth()->user()->role === \App\Enums\Role::Hr
 ? ['hr.home'=>'Dashboard','hr.employees.index'=>'Karyawan','hr.attendance.index'=>'Riwayat absensi','hr.enrollment'=>'Face enrollment','hr.corrections.index'=>'Approval koreksi','hr.leaves.index'=>'Pengajuan izin']
 : ['employee.home'=>'Beranda','employee.attendance.index'=>'Absensi saya','employee.corrections.index'=>'Koreksi saya','employee.leaves.index'=>'Izin saya']);
@endphp
<nav aria-label="Navigasi utama" class="mt-3 space-y-2">
@foreach($navigation as $route=>$label)
@php($active = str_ends_with($route,'.index') ? request()->routeIs(str_replace('.index','.*',$route)) : request()->routeIs($route))
<a href="{{ route($route) }}" @if($active) aria-current="page" @endif @class(['block rounded-xl px-4 py-3 text-sm font-medium','bg-magenta'=>$active,'hover:bg-white/10'=>!$active])>{{ $label }}</a>
@endforeach
</nav>
<p class="mt-6 text-sm text-purple-200">Portal kehadiran</p>
@else
<p class="text-sm leading-6 text-purple-100">Fondasi pengelolaan kehadiran yang tertata, dengan akses sesuai peran.</p>
@endauth
</div>
</aside>
<div class="min-w-0 flex-1">
<header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-white px-6 py-5 md:px-10">
<span class="font-semibold">{{ $title }}</span>
@auth
<div class="flex items-center gap-4"><span class="text-sm">{{ auth()->user()->name }}</span>
<form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm font-semibold text-magenta hover:underline">Keluar</button></form></div>
@else<span class="rounded-full bg-lavender px-3 py-1 text-xs font-semibold text-purple">Portal internal</span>@endauth
</header>
<main class="mx-auto max-w-6xl p-6 md:p-10">
@if(session('success'))<div role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif
@if($errors->any() && !request()->routeIs('login'))
<div role="alert" class="mb-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p class="font-semibold">Periksa kembali input Anda.</p><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
{{ $slot }}</main>
<footer class="px-6 pb-6 text-xs text-slate-500 md:px-10">FaceTrack AI / AI belum diaktifkan</footer>
</div>
</div>
</body></html>

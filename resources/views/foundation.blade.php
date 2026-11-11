<x-layout title="Beranda">
<p class="text-xs font-bold uppercase tracking-widest text-magenta">Workspace {{ strtoupper($role) }}</p>
<h1 class="mt-3 text-3xl font-bold tracking-tight text-purple">Selamat datang, {{ auth()->user()->name }}.</h1>
<p class="mt-3 max-w-2xl text-sm leading-6 text-slate-500">Fondasi aplikasi sudah disiapkan. Modul operasional akan ditambahkan secara bertahap sesuai hak akses Anda.</p>
<div class="mt-8 grid gap-5 lg:grid-cols-3">
@foreach(['Autentikasi'=>'Akses menggunakan session dan akun aktif.','Hak akses'=>'Halaman dilindungi pemeriksaan role pada server.','Status pengembangan'=>'Phase 1: fondasi, skema database, dan seed data sintetis.'] as $heading=>$description)
<section class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm"><span class="inline-block h-2 w-2 rounded-full bg-magenta"></span><h2 class="mt-4 font-semibold text-purple">{{ $heading }}</h2><p class="mt-2 text-sm leading-6 text-slate-500">{{ $description }}</p></section>
@endforeach
</div>
<section class="mt-7 rounded-2xl border border-purple/10 bg-white p-7">
<span class="rounded-full bg-lavender px-3 py-1 text-xs font-semibold text-purple">Belum diaktifkan</span>
<h2 class="mt-5 text-lg font-semibold text-purple">Integrasi face recognition</h2>
<p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Belum ada pengambilan wajah, penyimpanan biometrik, atau absensi otomatis. Layanan Python akan diintegrasikan pada fase pengembangan berikutnya.</p>
</section>
</x-layout>

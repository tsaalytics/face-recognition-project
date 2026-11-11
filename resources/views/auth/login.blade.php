<x-layout title="Masuk">
<div class="mx-auto my-6 max-w-md rounded-2xl border border-slate-100 bg-white p-7 shadow-sm md:my-12 md:p-10">
<p class="text-xs font-bold uppercase tracking-widest text-magenta">Selamat datang</p>
<h1 class="mt-3 text-3xl font-bold text-purple">Masuk ke workspace</h1>
<p class="mt-3 text-sm leading-6 text-slate-500">Gunakan akun perusahaan Anda. Untuk bantuan akses, hubungi Admin.</p>
@if($errors->any())<div role="alert" class="mt-6 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-5" data-submit-form>
@csrf
<div><label for="email" class="mb-2 block text-sm font-medium">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field" @error('email') aria-invalid="true" @enderror></div>
<div><label for="password" class="mb-2 block text-sm font-medium">Kata sandi</label><input id="password" name="password" type="password" required autocomplete="current-password" class="field"></div>
<label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1" class="accent-magenta"> Ingat saya</label>
<button class="w-full rounded-xl bg-magenta px-5 py-3 font-semibold text-white hover:opacity-90 disabled:opacity-60" data-submit-button>Masuk</button>
</form>
<p class="mt-6 text-xs leading-5 text-slate-500">Pendaftaran akun dikelola Admin. Pengenalan wajah belum tersedia pada fase ini.</p>
</div>
</x-layout>

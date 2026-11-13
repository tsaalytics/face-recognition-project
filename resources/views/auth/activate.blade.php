<x-layout title="Aktivasi Akun">
<div class="mx-auto max-w-md rounded-2xl bg-white p-8"><h1 class="text-2xl font-bold text-purple">Set Your Password</h1>
@if(!$valid)<p role="alert" class="mt-5 text-sm text-rose-800">Undangan tidak valid, sudah digunakan, atau kedaluwarsa. Hubungi Admin untuk mengirim ulang.</p>
@else<form method="POST" action="{{ route('invitation.activate') }}" class="mt-6 space-y-5" data-submit-form>@csrf
<input type="hidden" name="token" value="{{ $token }}">
<div><label class="filter-label" for="email">Email</label><input class="field" id="email" name="email" value="{{ $email }}" readonly></div>
<div><label class="filter-label" for="password">New Password</label><input class="field" id="password" name="password" type="password" autocomplete="new-password" required minlength="12"></div>
<div><label class="filter-label" for="password_confirmation">Confirm Password</label><input class="field" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12"></div>
<p class="text-xs text-slate-500">Minimal 12 karakter: huruf besar/kecil, angka, dan simbol.</p><button class="button w-full" data-submit-button>Activate Account</button></form>@endif
</div></x-layout>

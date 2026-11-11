<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string'], 'remember' => ['sometimes', 'boolean']];
    }

    public function authenticate(): void
    {
        $key = Str::transliterate(Str::lower((string) $this->string('email')).'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak percobaan. Coba dalam '.RateLimiter::availableIn($key).' detik.']);
        }
        if (! Auth::attempt([...$this->only('email', 'password'), 'is_active' => true], $this->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Email atau kata sandi tidak valid, atau akun tidak aktif.']);
        }
        RateLimiter::clear($key);
    }
}

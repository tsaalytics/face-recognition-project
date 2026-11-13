<?php

use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeIdGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('employees:backfill-ids {--dry-run}', function () {
    $eligible = Employee::whereNull('business_id')->whereNotNull('date_of_birth')->count();
    if ($this->option('dry-run')) {
        $this->info("Eligible: $eligible");

        return;
    }
    $count = 0;
    Employee::whereNull('business_id')->whereNotNull('date_of_birth')->orderBy('id')->eachById(function ($employee) use (&$count) {
        DB::transaction(function () use ($employee, &$count) {
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            if ($employee->business_id || ! $employee->date_of_birth) {
                return;
            }
            for ($i = 0; $i < 100; $i++) {
                $employee->business_id = app(EmployeeIdGenerator::class)->generate($employee->date_of_birth->toDateString());
                try {
                    $employee->save();
                    $count++;
                    break;
                } catch (UniqueConstraintViolationException $e) {
                    if (! str_contains($e->getMessage(), 'business_id') || $i === 99) {
                        throw $e;
                    }
                }
            }
        });
    });
    $this->info("Issued: $count; missing birth date: ".Employee::whereNull('business_id')->whereNull('date_of_birth')->count());
})->purpose('Backfill missing business IDs only when birth date is known.');

Artisan::command('admin:bootstrap {email} {--name=Administrator}', function () {
    if (User::where('role', 'admin')->exists()) {
        $this->error('Admin sudah tersedia.');

        return 1;
    }
    if (! $this->input->isInteractive()) {
        $this->error('Gunakan terminal interaktif untuk password rahasia.');

        return 1;
    }
    $password = $this->secret('Password Admin (minimal 12, huruf besar/kecil, angka, simbol)');
    $validator = Validator::make(['email' => $this->argument('email'), 'password' => $password], ['email' => ['required', 'email', 'unique:users'], 'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    $user = new User(['name' => $this->option('name'), 'email' => $this->argument('email'), 'password' => $password]);
    $user->forceFill(['role' => 'admin', 'account_status' => 'active', 'is_active' => true, 'activated_at' => now()])->save();
    $this->info('Admin dibuat. Tidak ada password yang dikirim via email.');
})->purpose('Bootstrap first Admin via secret interactive password, never public registration.');

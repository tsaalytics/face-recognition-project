<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class EmployeeIdGenerator
{
    public function randomPrefix(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function generate(string $birthDate): string
    {
        $suffix = Carbon::parse($birthDate)->format('Ymd');
        for ($i = 0; $i < 100; $i++) {
            $id = $this->randomPrefix().$suffix;
            if (! Employee::where('business_id', $id)->exists()) {
                return $id;
            }
        }
        throw ValidationException::withMessages(['date_of_birth' => 'ID belum dapat diterbitkan. Coba kembali atau hubungi administrator.']);
    }
}

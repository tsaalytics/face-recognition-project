<?php

namespace App\Contracts;

use App\Models\Employee;

interface FaceRecognitionService
{
    public function isAvailable(): bool;

    public function enroll(Employee $employee, string $privateImagePath): never;

    public function verify(string $privateImagePath): never;
}

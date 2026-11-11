<?php

namespace App\Services;

use App\Contracts\FaceRecognitionService;
use App\Models\Employee;

class DisabledFaceRecognitionService implements FaceRecognitionService
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function enroll(Employee $employee, string $privateImagePath): never
    {
        throw new \LogicException('AI belum diaktifkan.');
    }

    public function verify(string $privateImagePath): never
    {
        throw new \LogicException('AI belum diaktifkan.');
    }
}

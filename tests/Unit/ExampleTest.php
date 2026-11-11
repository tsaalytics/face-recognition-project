<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\DisabledFaceRecognitionService;
use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function test_disabled_ai_refuses_enrollment(): void
    {
        $service = new DisabledFaceRecognitionService;
        $this->assertFalse($service->isAvailable());
        $this->expectException(\LogicException::class);
        $service->enroll(new Employee, 'unused');
    }
}

<?php

namespace Tests\Unit;

use App\Services\AttendanceSchedule;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AttendanceScheduleTest extends TestCase
{
    public function test_schedule_uses_business_timezone_instead_of_input_timezone(): void
    {
        $schedule = app(AttendanceSchedule::class)->current(CarbonImmutable::parse('2026-11-18 18:00:00', 'UTC'));
        $this->assertSame('check_out', $schedule['mode']);
        $this->assertSame('2026-11-18', $schedule['attendance_date']);
        $this->assertSame('2026-11-19 01:00:00', $schedule['time']->format('Y-m-d H:i:s'));
    }

    public function test_configured_boundaries_are_respected_without_mutating_input_time(): void
    {
        config(['attendance.schedule.check_in_from' => '07:00:00']);
        $instant = CarbonImmutable::parse('2026-11-18 06:30:00', 'Asia/Jakarta');
        $schedule = app(AttendanceSchedule::class)->current($instant);
        $this->assertSame('closed', $schedule['mode']);
        $this->assertNull($schedule['attendance_date']);
        $this->assertSame('06:30:00', $instant->format('H:i:s'));
    }

    public function test_invalid_schedule_fails_closed(): void
    {
        config(['attendance.schedule.check_in_from' => '04:00:00']);
        $this->expectException(\LogicException::class);
        app(AttendanceSchedule::class)->current();
    }

    public function test_early_checkout_rolls_back_across_month_and_year_boundaries(): void
    {
        foreach (['2027-01-01' => '2026-12-31', '2026-12-01' => '2026-11-30'] as $date => $expected) {
            $schedule = app(AttendanceSchedule::class)->current(CarbonImmutable::parse($date.' 01:00:00', 'Asia/Jakarta'));
            $this->assertSame('check_out', $schedule['mode']);
            $this->assertSame($expected, $schedule['attendance_date']);
        }
    }
}

<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LogicException;

class AttendanceSchedule
{
    public function current(?CarbonInterface $instant = null): array
    {
        $now = ($instant ? CarbonImmutable::instance($instant) : CarbonImmutable::now())
            ->setTimezone(config('attendance.timezone'))->setMicrosecond(0);
        $schedule = config('attendance.schedule');
        $earlyEnd = $schedule['previous_day_checkout_until'] ?? '';
        $checkIn = $schedule['check_in_from'] ?? '';
        $checkOut = $schedule['check_out_from'] ?? '';
        foreach ([$earlyEnd, $checkIn, $checkOut] as $boundary) {
            if (! is_string($boundary) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/D', $boundary)) {
                throw new LogicException('Konfigurasi jadwal absensi tidak valid.');
            }
        }
        if (! ($earlyEnd < $checkIn && $checkIn < $checkOut)) {
            throw new LogicException('Urutan jadwal absensi tidak valid.');
        }
        $time = $now->format('H:i:s');
        $mode = match (true) {
            $time < $earlyEnd => 'check_out',
            $time < $checkIn => 'closed',
            $time < $checkOut => 'check_in',
            default => 'check_out',
        };
        $date = $time < $earlyEnd ? $now->subDay()->toDateString() : $now->toDateString();
        $onTimeUntil = $now->setTimeFromTimeString(config('attendance.shift_start'))->addMinutes(config('attendance.late_grace_minutes'));

        return [
            'mode' => $mode,
            'attendance_date' => $mode === 'closed' ? null : $date,
            'time' => $now,
            // Preserve database status names: present means On Time, late means Late.
            'status' => $mode === 'check_in' ? ($now->gt($onTimeUntil) ? 'late' : 'present') : null,
            'session' => config('attendance.default_session'),
        ];
    }
}

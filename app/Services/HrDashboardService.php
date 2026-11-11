<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveRequest;

class HrDashboardService
{
    public function data(): array
    {
        $today = fn ($q) => $q->whereDate('attendance_date', today())->whereNotNull('check_in_at')->whereIn('status', ['present', 'late']);
        $active = Employee::where('is_active', true)->count();
        $present = Employee::where('is_active', true)->whereHas('attendanceRecords', $today)->count();
        $late = Employee::where('is_active', true)->whereHas('attendanceRecords', fn ($q) => $q->whereDate('attendance_date', today())->where('status', 'late')->whereNotNull('check_in_at'))->count();
        $divisions = Division::withCount([
            'employees as active_count' => fn ($q) => $q->where('is_active', true),
            'employees as present_count' => fn ($q) => $q->where('is_active', true)->whereHas('attendanceRecords', $today),
        ])->orderBy('name')->get();
        $counts = AttendanceRecord::whereHas('employee', fn ($q) => $q->where('is_active', true))
            ->whereDate('attendance_date', '>=', today()->subDays(6))->whereDate('attendance_date', '<=', today())
            ->whereNotNull('check_in_at')->whereIn('status', ['present', 'late'])
            ->selectRaw('DATE(attendance_date) as day, COUNT(DISTINCT employee_id) as total')->groupByRaw('DATE(attendance_date)')->pluck('total', 'day');
        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $trend[] = ['label' => $day->format('d/m'), 'count' => (int) ($counts[$day->toDateString()] ?? 0)];
        }

        return ['active' => $active, 'present' => $present, 'late' => $late, 'absent' => max(0, $active - $present), 'divisions' => $divisions, 'trend' => $trend,
            'pendingCorrections' => AttendanceCorrectionRequest::where('status', 'pending')->count(),
            'pendingLeaves' => LeaveRequest::where('status','pending')->count()];
    }
}

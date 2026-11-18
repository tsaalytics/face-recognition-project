<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\Employee;

class DashboardPresentationService
{
    public function data(bool $hr = false): array
    {
        $year = today()->year;
        $daily = AttendanceRecord::whereYear('attendance_date', $year)
            ->whereDate('attendance_date', '<=', today())
            ->whereNotNull('check_in_at')->whereIn('status', ['present', 'late'])
            ->selectRaw('DATE(attendance_date) as day, COUNT(DISTINCT employee_id) as total')
            ->groupByRaw('DATE(attendance_date)')->get();
        $monthly = array_fill(1, 12, 0);
        foreach ($daily as $day) {
            $monthly[(int) substr($day->day, 5, 2)] += (int) $day->total;
        }
        $lateCount = Employee::where('is_active', true)->whereHas('attendanceRecords', fn ($q) => $q
            ->whereDate('attendance_date', today())->whereNotNull('check_in_at')->where('status', 'late'))->count();

        if ($hr) {
            $notifications = collect();
            foreach ([LeaveRequest::class => 'Pengajuan izin', AttendanceCorrectionRequest::class => 'Koreksi absensi'] as $model => $type) {
                foreach ($model::with('employee')->latest('submitted_at')->limit(5)->get() as $item) {
                    $notifications->push(['name' => $item->employee?->name ?? 'Karyawan', 'type' => $type,
                        'status' => $item->status, 'date' => $item->submitted_at,
                        'url' => route($model === LeaveRequest::class ? 'hr.leaves.show' : 'hr.corrections.show', $item)]);
                }
            }
            $notifications = $notifications->sortByDesc('date')->take(5);
        } else {
            $notifications = AttendanceRecord::with('employee')->whereNotNull('check_in_at')
                ->latest('check_in_at')->limit(5)->get()->map(fn ($item) => [
                    'name' => $item->employee?->name ?? 'Karyawan', 'type' => 'Absensi', 'status' => $item->status,
                    'url' => route('admin.attendance.index', ['q' => $item->employee?->employee_number]),
                ]);
        }

        return compact('year', 'monthly', 'lateCount', 'notifications');
    }
}

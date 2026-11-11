<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;

class AdminMonitoringService
{
    public function dashboard(): array
    {
        $active = Employee::where('is_active', true)->count();
        $present = Employee::where('is_active', true)->whereHas('attendanceRecords', fn ($q) => $q->whereDate('attendance_date', today())->whereNotNull('check_in_at')->whereIn('status', ['present', 'late']))->count();

        return ['employeeCount' => Employee::count(), 'activeEmployeeCount' => $active, 'presentCount' => $present, 'absentCount' => max(0, $active - $present), 'userCount' => User::count()];
    }

    public function employees(array $f)
    {
        return Employee::with(['division', 'user'])
            ->when($f['q'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($f['division_id'] ?? null, fn ($q, $id) => $q->where('division_id', $id))
            ->when(isset($f['active']), fn ($q) => $q->where('is_active', $f['active']))
            ->orderBy('name')->orderBy('id')->paginate(15)->withQueryString();
    }

    public function attendanceQuery(array $f)
    {
        return AttendanceRecord::with('employee.division')
            ->when($f['q'] ?? null, fn ($q, $s) => $q->whereHas('employee', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%"))))
            ->when($f['division_id'] ?? null, fn ($q, $id) => $q->whereHas('employee', fn ($q) => $q->where('division_id', $id)))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('attendance_date', '>=', $d))
            ->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('attendance_date', '<=', $d))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('attendance_date')->orderByDesc('id');
    }

    public function attendance(array $f)
    {
        return $this->attendanceQuery($f)->paginate(15)->withQueryString();
    }
}

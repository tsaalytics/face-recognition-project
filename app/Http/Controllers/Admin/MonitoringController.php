<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MonitorRequest;
use App\Models\AttendanceRecord;
use App\Models\Division;
use App\Models\Employee;
use App\Services\AdminMonitoringService;
use Illuminate\Support\Facades\Gate;

class MonitoringController extends Controller
{
    public function dashboard(AdminMonitoringService $service)
    {
        return view('admin.dashboard', array_merge($service->dashboard(), app(\App\Services\DashboardPresentationService::class)->data()));
    }

    public function employees(MonitorRequest $request, AdminMonitoringService $service)
    {
        Gate::authorize('viewAny', Employee::class);

        return view('admin.employees', ['employees' => $service->employees($request->validated(), (int) $request->validated('per_page', 10)), 'divisions' => Division::orderBy('name')->get()]);
    }

    public function attendance(MonitorRequest $request, AdminMonitoringService $service)
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        return view('admin.attendance', ['records' => $service->attendance($request->validated(), (int) $request->validated('per_page', 10)), 'divisions' => Division::orderBy('name')->get()]);
    }

    public function employee(Employee $employee)
    {
        Gate::authorize('view', $employee);
        $employee->load('division');

        return response()->view('employees.show', ['employee' => $employee, 'role' => 'admin'])
            ->header('Cache-Control', 'private, no-store');
    }
}

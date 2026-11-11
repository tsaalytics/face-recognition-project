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
        return view('admin.dashboard', $service->dashboard());
    }

    public function employees(MonitorRequest $request, AdminMonitoringService $service)
    {
        Gate::authorize('viewAny', Employee::class);

        return view('admin.employees', ['employees' => $service->employees($request->validated()), 'divisions' => Division::orderBy('name')->get()]);
    }

    public function attendance(MonitorRequest $request, AdminMonitoringService $service)
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        return view('admin.attendance', ['records' => $service->attendance($request->validated()), 'divisions' => Division::orderBy('name')->get()]);
    }
}

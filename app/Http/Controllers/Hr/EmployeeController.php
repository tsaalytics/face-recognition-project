<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeRequest;
use App\Http\Requests\Hr\MonitorRequest;
use App\Models\Division;
use App\Models\Employee;
use App\Models\User;
use App\Services\AdminMonitoringService;
use App\Services\EmployeeProfileService;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function index(MonitorRequest $request, AdminMonitoringService $service)
    {
        Gate::authorize('viewAny', Employee::class);

        return view('hr.employees.index', ['employees' => $service->employees($request->validated()), 'divisions' => Division::orderBy('name')->get()]);
    }

    private function form(Employee $employee)
    {
        $accounts = User::whereIn('role', ['employee', 'hr'])->where(fn ($q) => $q->where('is_active', true)->when($employee->user_id, fn ($q, $id) => $q->orWhere('id', $id)))->where(function ($q) use ($employee) {
            $q->whereDoesntHave('employee');
            if ($employee->user_id) {
                $q->orWhere('id', $employee->user_id);
            }
        })->orderBy('name')->get();

        return view('hr.employees.form', ['employee' => $employee, 'divisions' => Division::orderBy('name')->get(), 'accounts' => $accounts]);
    }

    public function create()
    {
        Gate::authorize('create', Employee::class);

        return $this->form(new Employee);
    }

    public function edit(Employee $employee)
    {
        Gate::authorize('update', $employee);

        return $this->form($employee);
    }

    public function store(EmployeeRequest $request, EmployeeProfileService $service)
    {
        $service->save($request->user(), $request->validated());

        return redirect()->route('hr.employees.index')->with('success', 'Profil karyawan berhasil dibuat.');
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeProfileService $service)
    {
        $service->save($request->user(), $request->validated(), $employee);

        return redirect()->route('hr.employees.index')->with('success', 'Profil karyawan berhasil diperbarui.');
    }
}

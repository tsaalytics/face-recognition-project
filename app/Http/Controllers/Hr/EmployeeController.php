<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeRequest;
use App\Http\Requests\Hr\EmployeeIndexRequest;
use App\Models\Division;
use App\Models\Employee;
use App\Models\FaceEnrollmentAudit;
use App\Models\User;
use App\Services\AdminMonitoringService;
use App\Services\EmployeeProfileService;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    public function index(EmployeeIndexRequest $request, AdminMonitoringService $service)
    {
        Gate::authorize('viewAny', Employee::class);

        return view('hr.employees.index', ['employees' => $service->employees($request->validated(), (int) $request->validated('per_page', 10)), 'divisions' => Division::orderBy('name')->get()]);
    }

    private function form(Employee $employee)
    {
        $accounts = User::whereIn('role', ['employee', 'hr'])->where(fn ($q) => $q->where('is_active', true)->when($employee->user_id, fn ($q, $id) => $q->orWhere('id', $id)))->where(function ($q) use ($employee) {
            $q->whereDoesntHave('employee');
            if ($employee->user_id) {
                $q->orWhere('id', $employee->user_id);
            }
        })->orderBy('name')->get();

        return response()->view('hr.employees.form', ['employee' => $employee, 'divisions' => Division::orderBy('name')->get(), 'accounts' => $accounts,
            'sampleCount' => $employee->exists ? $employee->faceEmbeddings()->count() : 0,
            'enrolledAt' => $employee->exists ? $employee->faceEmbeddings()->max('enrolled_at') : null,
            'enrollmentAudits' => $employee->exists ? FaceEnrollmentAudit::where('employee_id', $employee->id)->latest('id')->paginate(10, ['*'], 'audit_page') : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function create()
    {
        Gate::authorize('create', Employee::class);

        return $this->form(new Employee);
    }

    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);
        $employee->load('division');

        return response()->view('employees.show', ['employee' => $employee, 'role' => 'hr'])
            ->header('Cache-Control', 'private, no-store');
    }

    public function edit(Employee $employee)
    {
        Gate::authorize('update', $employee);

        return $this->form($employee);
    }

    public function store(EmployeeRequest $request, EmployeeProfileService $service)
    {
        $employee = $service->save($request->user(), $request->validated());

        if ($request->expectsJson()) {
            return $this->savedProfile($employee, 201);
        }

        return redirect()->route('hr.employees.index')->with('success', 'Profil karyawan berhasil dibuat.');
    }

    public function update(EmployeeRequest $request, Employee $employee, EmployeeProfileService $service)
    {
        $employee = $service->save($request->user(), $request->validated(), $employee);

        if ($request->expectsJson()) {
            return $this->savedProfile($employee);
        }

        return redirect()->route('hr.employees.index')->with('success', 'Profil karyawan berhasil diperbarui.');
    }

    private function savedProfile(Employee $employee, int $status = 200)
    {
        $employee->refresh();

        return response()->json([
            'business_id' => $employee->business_id,
            'update_url' => route('hr.employees.update', $employee),
            'edit_url' => route('hr.employees.edit', $employee),
            'enrollment_url' => route('hr.faces.store', $employee),
            'enrollment_version' => $employee->face_enrollment_version,
        ], $status)->header('Cache-Control', 'private, no-store');
    }
}

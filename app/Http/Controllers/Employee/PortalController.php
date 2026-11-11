<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\CorrectionRequest;
use App\Http\Requests\Employee\HistoryRequest;
use App\Http\Requests\Employee\LeaveRequest as LeaveFormRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\EmployeeSubmissionService;
use App\Services\PrivateEvidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PortalController extends Controller
{
    private function profile(Request $request): ?Employee
    {
        return $request->user()->employee()->with('division')->first();
    }

    public function home(Request $request)
    {
        return view('employee.home', ['employee' => $this->profile($request)]);
    }

    public function attendance(HistoryRequest $request)
    {
        $employee = $this->profile($request);
        $f = $request->validated();
        $records = AttendanceRecord::whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($f['from'] ?? null, fn ($q, $d) => $q->whereDate('attendance_date', '>=', $d))->when($f['to'] ?? null, fn ($q, $d) => $q->whereDate('attendance_date', '<=', $d))
            ->orderByDesc('attendance_date')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('employee.attendance', compact('employee', 'records'));
    }

    private function listing(HistoryRequest $request, string $model, string $kind)
    {
        $f = $request->validated();
        $employee = $this->profile($request);
        $requests = $model::whereHas('employee', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->orderByDesc('submitted_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('employee.requests.index', compact('requests', 'kind', 'employee'));
    }

    public function corrections(HistoryRequest $request)
    {
        return $this->listing($request, AttendanceCorrectionRequest::class, 'corrections');
    }

    public function leaves(HistoryRequest $request)
    {
        return $this->listing($request, LeaveRequest::class, 'leaves');
    }

    public function createCorrection()
    {
        Gate::authorize('create', AttendanceCorrectionRequest::class);

        return view('employee.corrections.create');
    }

    public function createLeave()
    {
        Gate::authorize('create', LeaveRequest::class);

        return view('employee.leaves.create');
    }

    public function storeCorrection(CorrectionRequest $request, EmployeeSubmissionService $service)
    {
        $correction = $service->correction($request->user(), $request->validated(), $request->file('photo'));
        $url = route('employee.corrections.show', $correction);
        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Koreksi diajukan. Menunggu keputusan HR.');

            return response()->json(['redirect' => $url], 201);
        }

        return redirect($url)->with('success', 'Koreksi diajukan. Menunggu keputusan HR.');
    }

    public function storeLeave(LeaveFormRequest $request, EmployeeSubmissionService $service)
    {
        $leave = $service->leave($request->user(), $request->validated(), $request->file('attachment'));

        return redirect()->route('employee.leaves.show', $leave)->with('success', 'Pengajuan izin dikirim. Menunggu keputusan HR.');
    }

    private function detail($request, string $kind)
    {
        Gate::authorize('view', $request);
        $request->load(['employee.division', 'approvalLogs.reviewer']);

        return view('employee.requests.show', compact('request', 'kind'));
    }

    public function correction(AttendanceCorrectionRequest $correction)
    {
        return $this->detail($correction, 'corrections');
    }

    public function leave(LeaveRequest $leave)
    {
        return $this->detail($leave, 'leaves');
    }

    public function evidence(AttendanceCorrectionRequest $correction, PrivateEvidenceService $service)
    {
        return $service->response($correction, $correction->evidence_path, true);
    }

    public function attachment(LeaveRequest $leave,PrivateEvidenceService $service)
    {
        return $service->response($leave,$leave->attachment_path,false);
    }
}

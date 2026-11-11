<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApprovalFilterRequest;
use App\Http\Requests\Hr\DecisionRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Services\ApprovalService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ApprovalController extends Controller
{
    private function listing(ApprovalFilterRequest $request, string $model, string $kind)
    {
        $f = $request->validated();
        $requests = $model::with('employee.division')
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['q'] ?? null, fn ($q, $s) => $q->whereHas('employee', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('employee_number', 'like', "%$s%"))))
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('hr.approvals.index', compact('requests', 'kind'));
    }

    public function corrections(ApprovalFilterRequest $request)
    {
        return $this->listing($request, AttendanceCorrectionRequest::class, 'corrections');
    }

    public function leaves(ApprovalFilterRequest $request)
    {
        return $this->listing($request, LeaveRequest::class, 'leaves');
    }

    private function detail($request, string $kind)
    {
        Gate::authorize('view', $request);
        $request->load(['employee.division', 'approvalLogs.reviewer']);

        return view('hr.approvals.show', compact('request', 'kind'));
    }

    public function correction(AttendanceCorrectionRequest $correction)
    {
        return $this->detail($correction, 'corrections');
    }

    public function leave(LeaveRequest $leave)
    {
        return $this->detail($leave, 'leaves');
    }

    public function decideCorrection(DecisionRequest $request, AttendanceCorrectionRequest $correction, ApprovalService $service)
    {
        $f = $request->validated();
        $service->decide($request->user(), $correction, $f['decision'], $f['notes'] ?? null);

        return redirect()->route('hr.corrections.show', $correction)->with('success', 'Keputusan koreksi berhasil dicatat.');
    }

    public function decideLeave(DecisionRequest $request, LeaveRequest $leave, ApprovalService $service)
    {
        $f = $request->validated();
        $service->decide($request->user(), $leave, $f['decision'], $f['notes'] ?? null);

        return redirect()->route('hr.leaves.show', $leave)->with('success', 'Keputusan izin berhasil dicatat.');
    }

    private function file($request, ?string $path, bool $image)
    {
        Gate::authorize('view', $request);
        abort_unless($path && preg_match('#^(corrections|leaves|demo)/[a-zA-Z0-9/_\-.]+$#', $path) && ! str_contains($path, '..'), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        $allowed = $image ? ['image/jpeg', 'image/png', 'image/webp'] : ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if ($request instanceof AttendanceCorrectionRequest && $request->is_demo) {
            $allowed[] = 'text/plain';
        }
        abort_unless(in_array($mime, $allowed, true), 404);

        return $disk->response($path, 'evidence.'.pathinfo($path, PATHINFO_EXTENSION), ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'], $image && $mime !== 'text/plain' ? 'inline' : 'attachment');
    }

    public function evidence(AttendanceCorrectionRequest $correction)
    {
        return $this->file($correction,$correction->evidence_path,true);
    }

    public function attachment(LeaveRequest $leave)
    {
        return $this->file($leave,$leave->attachment_path,false);
    }
}

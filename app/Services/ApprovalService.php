<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function decide(User $actor, AttendanceCorrectionRequest|LeaveRequest $request, string $decision, ?string $notes): void
    {
        abort_unless(in_array($decision, ['approved', 'rejected'], true), 422);
        DB::transaction(function () use ($actor, $request, $decision, $notes) {
            // Shared employee lock serializes leave and correction decisions, including new attendance rows.
            $employee = Employee::whereKey($request->employee_id)->lockForUpdate()->firstOrFail();
            $request = $request->newQuery()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $request->setRelation('employee', $employee);
            Gate::forUser($actor)->authorize($decision === 'approved' ? 'approve' : 'reject', $request);
            if ($decision === 'rejected' && trim($notes ?? '') === '') {
                $this->fail('Catatan penolakan wajib diisi.');
            }
            if ($decision === 'approved') {
                if (! $employee->is_active) {
                    $this->fail('Karyawan nonaktif tidak dapat disetujui.');
                }
                if ($request instanceof AttendanceCorrectionRequest) {
                    $this->correct($request);
                } else {
                    $this->leave($request);
                }
            }
            $request->status = $decision;
            $request->save();
            $log = new ApprovalLog;
            $log->forceFill([
                'attendance_correction_request_id' => $request instanceof AttendanceCorrectionRequest ? $request->id : null,
                'leave_request_id' => $request instanceof LeaveRequest ? $request->id : null,
                'reviewed_by' => $actor->id, 'from_status' => 'pending', 'to_status' => $decision,
                'notes' => $notes, 'reviewed_at' => now(),
            ])->save();
        }, 3);
    }

    private function correct(AttendanceCorrectionRequest $request): void
    {
        $time = $request->submitted_at;
        $date = $time->toDateString();
        if ($request->session !== config('attendance.default_session') || $time->toDateString() !== $date || $time->isFuture()) {
            $this->fail('Koreksi hanya mendukung sesi regular pada tanggal yang sama dan waktu yang telah berlalu.');
        }
        if ($request->latitude < -90 || $request->latitude > 90 || $request->longitude < -180 || $request->longitude > 180) {
            $this->fail('Koordinat tidak valid.');
        }
        if (! $request->is_demo) {
            $disk = Storage::disk('local');
            $path = $request->evidence_path;
            if (! preg_match('#^corrections/[a-zA-Z0-9/_\-.]+$#', $path) || str_contains($path, '..') || ! $disk->exists($path) || ! in_array($disk->mimeType($path), ['image/jpeg', 'image/png', 'image/webp'], true) || $disk->size($path) > 5 * 1024 * 1024) {
                $this->fail('Bukti foto privat tidak tersedia atau tidak valid.');
            }
        }
        if (LeaveRequest::where('employee_id', $request->employee_id)->where('status', 'approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
            $this->fail('Tanggal koreksi berbenturan dengan izin yang disetujui.');
        }
        $record = AttendanceRecord::where('employee_id', $request->employee_id)->whereDate('attendance_date', $date)->where('session', $request->session)->lockForUpdate()->first();
        if ($record?->status === 'leave') {
            $this->fail('Catatan izin tidak dapat ditimpa koreksi.');
        }
        $record ??= new AttendanceRecord(['employee_id' => $request->employee_id, 'attendance_date' => $date, 'session' => $request->session]);
        if ($request->type === 'check_in') {
            if ($record->check_out_at && $time->gt($record->check_out_at)) {
                $this->fail('Jam masuk tidak boleh setelah jam pulang.');
            }
            $record->check_in_at = $time;
        } else {
            if (! $record->check_in_at || $time->lt($record->check_in_at)) {
                $this->fail('Check-out membutuhkan check-in dan tidak boleh mendahuluinya.');
            }
            $record->check_out_at = $time;
        }
        $start = $record->check_in_at->copy()->setTimeFromTimeString(config('attendance.shift_start'))->addMinutes(config('attendance.late_grace_minutes'));
        $record->status = $record->check_in_at->gt($start) ? 'late' : 'present';
        $record->source = 'hr_approved_correction';
        $record->save();
    }

    private function leave(LeaveRequest $request): void
    {
        if ($request->end_date->lt($request->start_date) || $request->start_date->diffInDays($request->end_date) > 365) {
            $this->fail('Rentang izin tidak valid atau melebihi 366 hari.');
        }
        if (LeaveRequest::where('employee_id', $request->employee_id)->where('id', '!=', $request->id)->where('status', 'approved')->whereDate('start_date', '<=', $request->end_date)->whereDate('end_date', '>=', $request->start_date)->exists()) {
            $this->fail('Rentang tanggal berbenturan dengan izin yang telah disetujui.');
        }
        $records = AttendanceRecord::where('employee_id', $request->employee_id)->whereDate('attendance_date', '>=', $request->start_date)->whereDate('attendance_date', '<=', $request->end_date)->lockForUpdate()->get();
        if ($records->contains(fn ($r) => $r->check_in_at || $r->check_out_at || in_array($r->status, ['present', 'late'], true))) {
            $this->fail('Rentang izin memiliki catatan kehadiran. Tinjau kembali tanggal permohonan.');
        }
        for ($day = $request->start_date->copy(); $day->lte($request->end_date); $day->addDay()) {
            $date = $day->toDateString();
            $record = $records->first(fn ($r) => $r->attendance_date->toDateString() === $date && $r->session === config('attendance.default_session'))
             ?? new AttendanceRecord(['employee_id' => $request->employee_id, 'attendance_date' => $date, 'session' => config('attendance.default_session')]);
            $record->status = 'leave';
            $record->source = 'hr_approved';
            $record->save();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['decision' => $message]);
    }
}

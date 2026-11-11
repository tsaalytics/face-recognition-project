<?php

namespace App\Services;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmployeeSubmissionService
{
    private function employee(User $actor): Employee
    {
        $employee = Employee::where('user_id', $actor->id)->lockForUpdate()->first();
        abort_unless($employee?->is_active, 403, 'Profil belum terhubung atau nonaktif. Hubungi HR.');

        return $employee;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    private function store(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'local');
        if (! $path) {
            throw new \RuntimeException('Berkas bukti gagal disimpan. Coba kembali.');
        }

        return $path;
    }

    public function correction(User $actor, array $data, UploadedFile $photo): AttendanceCorrectionRequest
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $data, $photo, &$path) {
                $employee = $this->employee($actor);
                Gate::forUser($actor)->authorize('create', AttendanceCorrectionRequest::class);
                $time = Carbon::createFromFormat('!Y-m-d H:i', $data['attendance_date'].' '.$data['time'], config('app.timezone'));
                if ($time->isFuture()) {
                    $this->fail('time', 'Waktu absensi yang dimohonkan belum terjadi.');
                }
                if (AttendanceCorrectionRequest::where('employee_id', $employee->id)->whereDate('attendance_date', $data['attendance_date'])->where('session', config('attendance.default_session'))->where('type', $data['type'])->where('status', 'pending')->exists()) {
                    $this->fail('attendance_date', 'Masih ada koreksi pending untuk tanggal dan jenis absensi ini.');
                }
                if (LeaveRequest::where('employee_id', $employee->id)->where('status', 'approved')->whereDate('start_date', '<=', $data['attendance_date'])->whereDate('end_date', '>=', $data['attendance_date'])->exists()) {
                    $this->fail('attendance_date', 'Tanggal tersebut sudah memiliki izin yang disetujui. Hubungi HR.');
                }
                $path = $this->store($photo, 'corrections/'.$employee->id);
                $request = new AttendanceCorrectionRequest;
                $request->forceFill([
                    'employee_id' => $employee->id, 'attendance_date' => $data['attendance_date'], 'session' => config('attendance.default_session'),
                    'type' => $data['type'], 'reason' => $data['reason'], 'evidence_path' => $path,
                    'requested_at' => $time, 'captured_at' => Carbon::parse($data['captured_at'])->setTimezone(config('app.timezone')),
                    'submitted_at' => now(), 'latitude' => $data['latitude'], 'longitude' => $data['longitude'], 'status' => 'pending', 'is_demo' => false,
                ])->save();

                return $request;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
    }

    public function leave(User $actor, array $data, ?UploadedFile $attachment): LeaveRequest
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $data, $attachment, &$path) {
                $employee = $this->employee($actor);
                Gate::forUser($actor)->authorize('create', LeaveRequest::class);
                $start = Carbon::parse($data['start_date']);
                $end = Carbon::parse($data['end_date']);
                if ($start->gt($end) || $start->diffInDays($end) > 365) {
                    $this->fail('end_date', 'Rentang izin maksimal 366 hari dan tanggal selesai harus sesudah/sama dengan mulai.');
                }
                if (LeaveRequest::where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists()) {
                    $this->fail('start_date', 'Rentang tanggal berbenturan dengan izin pending atau approved.');
                }
                if (AttendanceRecord::where('employee_id', $employee->id)->whereDate('attendance_date', '>=', $data['start_date'])->whereDate('attendance_date', '<=', $data['end_date'])->where(fn ($q) => $q->whereNotNull('check_in_at')->orWhereNotNull('check_out_at')->orWhereIn('status', ['present', 'late']))->exists()) {
                    $this->fail('start_date', 'Rentang izin memiliki catatan kehadiran. Hubungi HR untuk peninjauan.');
                }
                if ($attachment) {
                    $path = $this->store($attachment, 'leaves/'.$employee->id);
                }
                $request = new LeaveRequest;
                $request->forceFill(['employee_id' => $employee->id, 'type' => $data['type'], 'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'reason' => $data['reason'], 'attachment_path' => $path, 'status' => 'pending', 'submitted_at' => now()])->save();

                return $request;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
    }
}

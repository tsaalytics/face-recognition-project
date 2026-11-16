<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FaceAttendanceService
{
    public function __construct(private FaceExtractionClient $client, private FaceEmbeddingMatcher $matcher) {}

    public function record(User $actor, array $data, int $expires): array
    {
        $threshold = config('face.similarity_threshold');
        $margin = config('face.identification_margin');
        if (! is_numeric($threshold) || ! is_finite((float) $threshold) || $threshold <= 0 || $threshold > 1) {
            $this->fail('Threshold absensi belum dikonfigurasi. Hubungi Admin.');
        }
        if (! is_numeric($margin) || ! is_finite((float) $margin) || $margin <= 0 || $margin > 1) {
            $this->fail('Margin identifikasi tidak valid. Hubungi Admin.');
        }
        $encoded = $data['samples'][0];
        if (! preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$#D', $encoded, $parts)) {
            $this->fail('Format frame kamera tidak valid.');
        }
        $bytes = base64_decode($parts[2], true);
        $info = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (! $info || strlen($bytes) > 1024 * 1024 || $info['mime'] !== $parts[1] || min($info[0], $info[1]) < 32 || max($info[0], $info[1]) > 4096 || $info[0] * $info[1] > 12000000) {
            $this->fail('Frame kamera tidak valid atau melebihi batas.');
        }
        $probe = $this->client->extract($bytes, $parts[1]);
        $scores = [];
        $versions = [];
        foreach (EmployeeFaceEmbedding::with('employee')->where('model_identifier', $probe['model_identifier'])->where('model_name', 'buffalo_m')->where('embedding_dimension', 512)->where('enrollment_status', 'enrolled')->get() as $row) {
            $employee = $row->employee;
            if (! $employee || ! $employee->is_active || ! $employee->business_id || $employee->enrollment_status !== 'enrolled') {
                continue;
            }
            $score = $this->matcher->cosineSimilarity($probe['embedding'], $row->face_embedding);
            $scores[$employee->id] = max($scores[$employee->id] ?? -1, $score);
            $versions[$employee->id] = $employee->face_enrollment_version;
        }
        arsort($scores);
        $ids = array_keys($scores);
        if (! $ids || $scores[$ids[0]] < (float) $threshold || (count($ids) > 1 && $scores[$ids[0]] - $scores[$ids[1]] < (float) $margin)) {
            $this->fail('Wajah tidak dikenali dengan cukup yakin. Coba ulang atau gunakan koreksi melalui HR.');
        }

        return DB::transaction(function () use ($actor, $data, $ids, $versions, $expires) {
            abort_unless($expires > now()->timestamp, 403, 'Sesi kiosk berakhir.');
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $actor->role->value === 'hr', 403);
            $employee = Employee::whereKey($ids[0])->lockForUpdate()->firstOrFail();
            if (! $employee->is_active || ! $employee->business_id || $employee->enrollment_status !== 'enrolled' || $employee->face_enrollment_version !== $versions[$employee->id]) {
                $this->fail('Enrollment berubah. Silakan scan ulang.');
            }
            $now = now(config('attendance.timezone'));
            $date = $now->toDateString();
            if (LeaveRequest::where('employee_id', $employee->id)->where('status', 'approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
                $this->fail('Karyawan memiliki izin yang disetujui hari ini.');
            }
            $record = AttendanceRecord::where('employee_id', $employee->id)->whereDate('attendance_date', $date)->where('session', config('attendance.default_session'))->lockForUpdate()->first();
            if ($record && ($record->source !== 'face_recognition' || $record->status === 'leave')) {
                $this->fail('Catatan existing dilindungi. Hubungi HR untuk koreksi.');
            }
            if ($data['type'] === 'check_in') {
                if ($record?->check_in_at) {
                    $this->fail('Check-in hari ini sudah tercatat.');
                }
                $record ??= new AttendanceRecord(['employee_id' => $employee->id, 'attendance_date' => $date, 'session' => config('attendance.default_session')]);
                $record->check_in_at = $now;
                $start = $now->copy()->setTimeFromTimeString(config('attendance.shift_start'))->addMinutes(config('attendance.late_grace_minutes'));
                $record->status = $now->gt($start) ? 'late' : 'present';
            } else {
                if (! $record?->check_in_at || $record->check_out_at || $now->lte($record->check_in_at)) {
                    $this->fail('Check-out membutuhkan check-in sebelumnya dan hanya dapat dicatat sekali.');
                }
                $record->check_out_at = $now;
            }
            $record->source = 'face_recognition';
            $record->save();

            return ['message' => 'Absensi berhasil dicatat.', 'employee' => $employee->business_id, 'type' => $data['type'], 'time' => $now->format('d-m-Y H:i:s').' WIB'];
        }, 3);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['samples' => $message]);
    }
}

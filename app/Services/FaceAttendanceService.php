<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\KioskDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FaceAttendanceService
{
    public function __construct(private FaceExtractionClient $client, private FaceEmbeddingMatcher $matcher, private AttendanceSchedule $schedule) {}

    public function record(User|KioskDevice $actor, array $data, int $expires): array
    {
        if ($this->schedule->current()['mode'] === 'closed') {
            $this->fail('Absensi belum tersedia. Silakan ikuti jadwal yang ditentukan.');
        }
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

        return DB::transaction(function () use ($actor, $ids, $versions, $expires) {
            abort_unless($expires > now()->timestamp, 403, 'Sesi kiosk berakhir.');
            if ($actor instanceof KioskDevice) {
                $actor = KioskDevice::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->authorized(), 403, 'Akses perangkat kiosk berakhir atau dicabut.');
            } else {
                // Compatibility for the pre-existing authenticated HR API only.
                $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->is_active && $actor->role->value === 'hr', 403);
            }
            $employee = Employee::whereKey($ids[0])->lockForUpdate()->firstOrFail();
            if (! $employee->is_active || ! $employee->business_id || $employee->enrollment_status !== 'enrolled' || $employee->face_enrollment_version !== $versions[$employee->id]) {
                $this->fail('Enrollment berubah. Silakan scan ulang.');
            }
            // Resolve again after inference/locks so crossing a boundary uses server commit-time rules.
            $schedule = $this->schedule->current();
            if ($schedule['mode'] === 'closed') {
                $this->fail('Absensi belum tersedia. Silakan ikuti jadwal yang ditentukan.');
            }
            $now = $schedule['time'];
            $date = $schedule['attendance_date'];
            $mode = $schedule['mode'];
            if (LeaveRequest::where('employee_id', $employee->id)->where('status', 'approved')->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->exists()) {
                $this->fail('Karyawan memiliki izin yang disetujui hari ini.');
            }
            $record = AttendanceRecord::where('employee_id', $employee->id)->whereDate('attendance_date', $date)->where('session', $schedule['session'])->lockForUpdate()->first();
            if ($record && ($record->source !== 'face_recognition' || $record->status === 'leave')) {
                $this->fail('Catatan existing dilindungi. Hubungi HR untuk koreksi.');
            }
            if ($mode === 'check_in') {
                if ($record?->check_in_at) {
                    $this->fail('Absensi untuk sesi ini sudah tercatat.');
                }
                $record ??= new AttendanceRecord(['employee_id' => $employee->id, 'attendance_date' => $date, 'session' => $schedule['session']]);
                $record->check_in_at = $now;
                $record->status = $schedule['status'];
            } else {
                if ($record?->check_out_at) {
                    $this->fail('Absensi untuk sesi ini sudah tercatat.');
                }
                if (! $record?->check_in_at || $now->lte($record->check_in_at)) {
                    $this->fail('Check-out membutuhkan check-in sebelumnya. Ajukan koreksi melalui HR.');
                }
                $record->check_out_at = $now;
            }
            $record->source = 'face_recognition';
            $record->save();

            return ['message' => 'Berhasil absen '.($mode === 'check_in' ? 'masuk' : 'pulang').' atas nama '.$employee->name.', pada '.$now->format('d-m-Y').' pukul '.$now->format('H:i:s').' WIB.',
                'employee' => $employee->business_id, 'employee_name' => $employee->name, 'status' => $record->status,
                'type' => $mode, 'attendance_date' => $date, 'time' => $now->format('d-m-Y H:i:s').' WIB'];
        }, 3);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['samples' => $message]);
    }
}

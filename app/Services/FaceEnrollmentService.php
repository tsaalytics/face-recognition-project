<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\FaceEnrollmentAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FaceEnrollmentService
{
    public function __construct(private FaceExtractionClient $client, private FaceEmbeddingMatcher $matcher) {}

    public function enroll(User $actor, Employee $employee, array $data): void
    {
        Gate::forUser($actor)->authorize('manageFace', $employee);
        $this->eligible($employee);
        $this->version($employee, (int) $data['enrollment_version']);
        $minimum = config('face.enrollment_consistency_threshold');
        $duplicate = config('face.enrollment_duplicate_threshold');
        if (! is_finite($minimum) || ! is_finite($duplicate) || $minimum <= 0 || $minimum >= $duplicate || $duplicate > 1) {
            $this->fail('Konfigurasi kualitas enrollment tidak valid. Hubungi Admin.');
        }
        $samples = [];
        // Validate every image before the first network call. No files or paths are created.
        foreach ($data['samples'] as $encoded) {
            if (! preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$#D', $encoded, $parts)) {
                $this->fail('Format sampel kamera tidak valid.');
            }
            $bytes = base64_decode($parts[2], true);
            $info = $bytes === false ? false : @getimagesizefromstring($bytes);
            if (! $info || strlen($bytes) > 1024 * 1024 || $info['mime'] !== $parts[1] || min($info[0], $info[1]) < 32 || max($info[0], $info[1]) > 4096 || $info[0] * $info[1] > 12000000) {
                $this->fail('Sampel kamera tidak valid atau melebihi batas 1 MiB/4096 pixel.');
            }
            $samples[] = [$bytes, $parts[1]];
        }
        $extracted = [];
        $deadline = microtime(true) + 25;
        foreach ($samples as [$bytes, $mime]) {
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                $this->fail('Proses enrollment melewati batas waktu. Silakan coba kembali.');
            }
            $sample = $this->client->extract($bytes, $mime, $remaining);
            foreach ($extracted as $other) {
                if ($sample['model_identifier'] !== $other['model_identifier'] || $this->matcher->cosineSimilarity($sample['embedding'], $other['embedding']) < $minimum) {
                    $this->fail('Sampel tidak konsisten. Gunakan wajah orang yang sama dan coba ulang.');
                }
            }
            $extracted[] = $sample;
        }
        $unique = [];
        foreach ($extracted as $sample) {
            foreach ($unique as $other) {
                if ($this->matcher->cosineSimilarity($sample['embedding'], $other['embedding']) >= $duplicate) {
                    continue 2;
                }
            }
            $unique[] = $sample;
        }
        DB::transaction(function () use ($actor, $employee, $data, $unique) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageFace', $employee);
            $this->eligible($employee);
            $this->version($employee, (int) $data['enrollment_version']);
            $action = $employee->faceEmbeddings()->exists() ? 're_enroll' : 'enroll';
            $employee->faceEmbeddings()->delete();
            $now = now();
            foreach ($unique as $sample) {
                (new EmployeeFaceEmbedding)->forceFill(['employee_id' => $employee->id, 'face_embedding' => $sample['embedding'],
                    'embedding_dimension' => 512, 'model_name' => 'buffalo_m', 'model_identifier' => $sample['model_identifier'],
                    'enrollment_status' => 'enrolled', 'quality' => $sample['quality'], 'enrolled_at' => $now])->save();
            }
            $employee->forceFill(['enrollment_status' => 'enrolled', 'face_enrollment_version' => $employee->face_enrollment_version + 1])->save();
            $this->audit($actor, $employee, $action, count($data['samples']), count($unique), $unique[0]['model_identifier'], $now);
        }, 3);
    }

    public function delete(User $actor, Employee $employee, int $version): void
    {
        DB::transaction(function () use ($actor, $employee, $version) {
            $actor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $employee = Employee::whereKey($employee->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manageFace', $employee);
            $this->version($employee, $version);
            abort_unless($employee->faceEmbeddings()->exists(), 409, 'Enrollment sudah kosong.');
            $employee->faceEmbeddings()->delete();
            $employee->forceFill(['enrollment_status' => 'not_enrolled', 'face_enrollment_version' => $version + 1])->save();
            $this->audit($actor, $employee, 'delete', 0, 0, null, now());
        }, 3);
    }

    private function audit(User $actor, Employee $employee, string $action, int $samples, int $stored, ?string $model, $now): void
    {
        (new FaceEnrollmentAudit)->forceFill(['employee_id' => $employee->id, 'actor_id' => $actor->id, 'action' => $action,
            'version' => $employee->face_enrollment_version, 'sample_count' => $samples, 'stored_count' => $stored,
            'model_identifier' => $model, 'consent_version' => $action === 'delete' ? null : 'biometric-demo-v1', 'occurred_at' => $now])->save();
    }

    private function eligible(Employee $employee): void
    {
        if (! $employee->is_active || ! $employee->business_id) {
            $this->fail('Enrollment memerlukan profil aktif dengan ID Karyawan yang sudah diterbitkan.');
        }
    }

    private function version(Employee $employee, int $version): void
    {
        abort_if($employee->face_enrollment_version !== $version, 409, 'Enrollment telah berubah. Muat ulang halaman.');
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['samples' => $message]);
    }
}

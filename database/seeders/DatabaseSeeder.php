<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeder hanya untuk local/testing.');
        }
        DB::transaction(function () {
            foreach (['admin' => Role::Admin, 'hr' => Role::Hr, 'employee' => Role::Employee] as $name => $role) {
                $u = User::firstOrNew(['email' => $name.'@facetrack.test']);
                $u->forceFill(['name' => 'Demo '.ucfirst($name), 'password' => 'DemoFaceTrack!2026', 'role' => $role, 'is_active' => true])->save();
            }
            $divisions = [];
            foreach (['Produksi', 'Quality Assurance', 'Logistik'] as $name) {
                $divisions[] = Division::firstOrCreate(['name' => $name]);
            }
            for ($i = 1; $i <= 6; $i++) {
                $employee = Employee::updateOrCreate(['employee_number' => sprintf('DEMO-%03d', $i)], [
                    'user_id' => $i === 1 ? User::where('email', 'employee@facetrack.test')->first()->id : null,
                    'division_id' => $divisions[($i - 1) % 3]->id, 'name' => 'Karyawan Demo '.$i, 'email' => 'demo'.$i.'@facetrack.test',
                    'position' => 'Operator Demo', 'is_active' => true,
                ]);
                $this->upsertDate(AttendanceRecord::class, ['employee_id' => $employee->id, 'attendance_date' => today()->subDay()->toDateString(), 'session' => 'regular'], [
                    'check_in_at' => today()->subDay()->setTime(8, $i === 2 ? 15 : 0), 'check_out_at' => today()->subDay()->setTime(17, 0),
                    'status' => $i === 2 ? 'late' : 'present', 'source' => 'seed_fixture',
                ]);
            }
            $employee = Employee::where('employee_number', 'DEMO-001')->first();
            // Gambar teks sintetis, tanpa wajah atau identitas nyata; bukan hasil kamera.
            Storage::disk('local')->put('demo/evidence.txt', 'SYNTHETIC FIXTURE - no camera image, GPS or biometric evidence.');
            AttendanceCorrectionRequest::unguarded(fn () => $this->upsertDate(AttendanceCorrectionRequest::class,
                ['employee_id' => $employee->id, 'attendance_date' => today()->toDateString(), 'session' => 'regular', 'type' => 'check_in'],
                ['reason' => 'DATA DEMO SINTETIS: permohonan contoh, bukan kegagalan AI nyata.', 'evidence_path' => 'demo/evidence.txt',
                    'captured_at' => now(), 'requested_at' => today()->setTime(8, 0), 'submitted_at' => now(), 'latitude' => 0, 'longitude' => 0, 'is_demo' => true, 'status' => 'pending']));
            $this->upsertDate(LeaveRequest::class, ['employee_id' => $employee->id, 'start_date' => today()->addDays(7)->toDateString(), 'end_date' => today()->addDays(8)->toDateString()], [
                'type' => 'annual', 'reason' => 'DATA DEMO SINTETIS: contoh pengajuan cuti.', 'submitted_at' => now(),
            ]);
        });
    }

    private function upsertDate(string $model, array $identity, array $values): void
    {
        $query = $model::query();
        foreach ($identity as $key => $value) {
            if (in_array($key, ['attendance_date', 'start_date', 'end_date'], true)) {
                $query->whereDate($key, $value);
            } else {
                $query->where($key, $value);
            }
        }
        $record = $query->first() ?? new $model;
        $record->fill([...$identity, ...$values])->save();
    }
}

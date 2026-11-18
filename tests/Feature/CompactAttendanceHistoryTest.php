<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactAttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $this->actingAs(User::where('email', 'hr@facetrack.test')->firstOrFail());
    }

    public function test_history_defaults_to_ten_rows_and_preserves_all_columns(): void
    {
        $this->get('/hr/attendance')->assertOk()
            ->assertViewHas('records', fn ($rows) => $rows->perPage() === 10)
            ->assertSee('aria-label="Breadcrumb"', false)->assertSee('data-list-filter', false)
            ->assertSee('Download CSV')
            ->assertSeeInOrder(['Tanggal</th>', 'ID Karyawan</th>', 'Nama Karyawan</th>', 'Divisi</th>', 'Masuk</th>', 'Pulang</th>', 'Status</th>', 'Sumber</th>'], false);
    }

    public function test_filters_pagination_and_export_preserve_the_same_results(): void
    {
        $employee = Employee::where('employee_number', 'DEMO-001')->firstOrFail();
        for ($i = 1; $i <= 26; $i++) {
            $day = today()->subDays($i + 1);
            AttendanceRecord::create(['employee_id' => $employee->id, 'attendance_date' => $day,
                'session' => 'regular', 'check_in_at' => $day->copy()->setTime(8, 15), 'status' => 'late', 'source' => 'seed_fixture']);
        }
        $filters = ['q' => 'DEMO-001', 'division_id' => $employee->division_id,
            'from' => today()->subDays(27)->toDateString(), 'to' => today()->subDays(2)->toDateString(), 'status' => 'late'];
        foreach ([5, 10, 25, 50] as $perPage) {
            $this->get('/hr/attendance?'.http_build_query($filters + ['per_page' => $perPage]))->assertOk()
                ->assertViewHas('records', fn ($rows) => $rows->total() === 26 && $rows->perPage() === $perPage && $rows->count() === min(26, $perPage))
                ->assertSee('5 filter aktif')
                ->assertSee('href="'.e(route('hr.attendance.export', $filters)).'"', false);
        }
        $this->get('/hr/attendance?'.http_build_query($filters + ['per_page' => 5, 'page' => 2]))->assertOk()
            ->assertViewHas('records', function ($rows) use ($filters) {
                parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $next);
                return $rows->currentPage() === 2 && $rows->count() === 5
                    && $next == $filters + ['per_page' => 5, 'page' => 3];
            });
        $csv = $this->get(route('hr.attendance.export', $filters))->assertOk()->streamedContent();
        $lines = preg_split('/\r?\n/', trim($csv));
        $this->assertCount(27, $lines);
        foreach (array_slice($lines, 1) as $line) {
            $row = str_getcsv($line, ',', '"', '');
            $this->assertSame('DEMO-001', $row[2]);
            $this->assertSame('late', $row[7]);
        }
        $this->get('/hr/attendance?q=NoMatchingEmployee')->assertOk()->assertSee('Tidak ada catatan absensi');
    }

    public function test_validation_and_access_remain_enforced_and_admin_defaults_to_ten_rows(): void
    {
        foreach (['0', '15', '1000', 'all', ['10']] as $limit) {
            $this->getJson('/hr/attendance?'.http_build_query(['per_page' => $limit]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
        }
        $this->getJson('/hr/attendance?from=2026-11-20&to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/hr/attendance?status=unknown')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->actingAs(User::where('email', 'admin@facetrack.test')->firstOrFail());
        $this->get('/hr/attendance')->assertForbidden();
        $this->get('/hr/attendance/export')->assertForbidden();
        $this->get('/admin/attendance')->assertOk()->assertViewHas('records', fn ($rows) => $rows->perPage() === 10);
    }
}

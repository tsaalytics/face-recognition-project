<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\FaceExtractionClient;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Support\EnforceRequestForgery;
use Tests\TestCase;

class FaceAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(8, 0));
        config(['face.similarity_threshold' => 0.8]);
        $this->employee = Employee::firstOrFail();
        $this->employee->forceFill(['is_active' => true, 'business_id' => 'TEST-64', 'enrollment_status' => 'enrolled'])->save();
        $this->embedding($this->employee);
        $this->mock(FaceExtractionClient::class)->shouldReceive('extract')->andReturn(['model_identifier' => 'buffalo_m:sha256:'.str_repeat('a', 64), 'embedding' => [1, ...array_fill(0, 511, 0)]]);
        $this->actingAs(User::where('email', 'hr@facetrack.test')->firstOrFail());
        $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
    }

    private function embedding(Employee $employee): void
    {
        (new EmployeeFaceEmbedding)->forceFill(['employee_id' => $employee->id, 'face_embedding' => [1, ...array_fill(0, 511, 0)], 'embedding_dimension' => 512, 'model_name' => 'buffalo_m', 'model_identifier' => 'buffalo_m:sha256:'.str_repeat('a', 64), 'enrollment_status' => 'enrolled', 'quality' => [], 'enrolled_at' => now()])->save();
    }

    private function data(string $type = 'check_in'): array
    {
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return ['consent' => true, 'type' => $type, 'samples' => ['data:image/png;base64,'.base64_encode($bytes)]];
    }

    public function test_check_in_checkout_and_duplicates_use_server_time(): void
    {
        $this->postJson('/hr/kiosk/scan', $this->data())->assertOk()->assertJsonPath('employee', 'TEST-64')->assertDontSee('embedding');
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $this->employee->id, 'attendance_date' => '2026-11-16 00:00:00', 'source' => 'face_recognition', 'status' => 'present']);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        $this->travelTo(now()->setTime(17, 0));
        $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
        $this->postJson('/hr/kiosk/scan', $this->data('check_out'))->assertOk();
        $this->postJson('/hr/kiosk/scan', $this->data('check_out'))->assertUnprocessable();
    }

    public function test_threshold_consent_expiry_and_checkout_fail_closed(): void
    {
        $this->travelTo(now()->setTime(17, 0));
        $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
        $this->postJson('/hr/kiosk/scan', $this->data('check_out'))->assertUnprocessable();
        $this->postJson('/hr/kiosk/scan', array_replace($this->data(), ['consent' => false]))->assertUnprocessable();
        config(['face.similarity_threshold' => null]);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        $this->withSession(['face_kiosk_expires' => now()->timestamp])->postJson('/hr/kiosk/scan', $this->data())->assertForbidden();
    }

    public function test_existing_correction_is_never_overwritten(): void
    {
        AttendanceRecord::create(['employee_id' => $this->employee->id, 'attendance_date' => '2026-11-16', 'session' => 'regular', 'check_in_at' => now(), 'status' => 'present', 'source' => 'hr_approved_correction']);
        $this->postJson('/hr/kiosk/scan', $this->data('check_out'))->assertUnprocessable();
        $this->assertNull(AttendanceRecord::where('source', 'hr_approved_correction')->firstOrFail()->check_out_at);
    }

    public function test_ambiguous_gallery_and_inactive_profiles_are_rejected(): void
    {
        $other = Employee::where('id', '!=', $this->employee->id)->firstOrFail();
        $other->forceFill(['is_active' => true, 'business_id' => 'TEST-OTHER', 'enrollment_status' => 'enrolled'])->save();
        $this->embedding($other);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        Employee::query()->update(['is_active' => false]);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
    }

    public function test_kiosk_is_hr_only_and_can_be_closed(): void
    {
        $this->get('/hr/kiosk')->assertRedirect(route('hr.kiosk-devices.index'));
        $this->post('/hr/kiosk/stop')->assertRedirect('/hr/kiosk');
        $this->postJson('/hr/kiosk/scan', $this->data())->assertForbidden();
        foreach (['admin', 'employee'] as $role) {
            $this->actingAs(User::where('email', $role.'@facetrack.test')->firstOrFail())->get('/hr/kiosk')->assertForbidden();
            $this->post('/hr/kiosk/start')->assertForbidden();
            $this->postJson('/hr/kiosk/scan', $this->data())->assertForbidden();
        }
    }

    public function test_approved_leave_blocks_attendance(): void
    {
        (new LeaveRequest)->forceFill(['employee_id' => $this->employee->id, 'type' => 'permission', 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'reason' => 'Test', 'status' => 'approved', 'submitted_at' => now()])->save();
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
    }

    public function test_enrollment_changed_during_inference_is_rejected(): void
    {
        try {
            // Simulate re-enrollment after the gallery employee snapshot is hydrated.
            Employee::retrieved(function ($employee) {
                Employee::whereKey($employee->id)->increment('face_enrollment_version');
            });
            $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        } finally {
            Employee::flushEventListeners();
        }
    }

    public function test_session_expiring_during_inference_is_rejected(): void
    {
        $this->mock(FaceExtractionClient::class)->shouldReceive('extract')->andReturnUsing(function () {
            $this->travel(16)->minutes();

            return ['model_identifier' => 'buffalo_m:sha256:'.str_repeat('a', 64), 'embedding' => [1, ...array_fill(0, 511, 0)]];
        });
        $this->postJson('/hr/kiosk/scan', $this->data())->assertForbidden();
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
    }

    public function test_invalid_images_and_model_mismatch_do_not_write_attendance(): void
    {
        $this->postJson('/hr/kiosk/scan', array_replace($this->data(), ['samples' => ['data:image/png;base64,YmFk']]))->assertUnprocessable();
        EmployeeFaceEmbedding::query()->update(['model_identifier' => 'buffalo_m:sha256:'.str_repeat('b', 64)]);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
    }

    public function test_scan_requires_real_csrf_token(): void
    {
        $this->app->bind(PreventRequestForgery::class, EnforceRequestForgery::class);
        $this->withSession(['_token' => 'expected'])->postJson('/hr/kiosk/scan', $this->data())->assertStatus(419);
    }

    public static function checkInTimes(): array
    {
        return [['06:00:00', 'present'], ['07:59:59', 'present'], ['08:00:00', 'present'], ['08:00:01', 'late'], ['16:59:00', 'late'], ['16:59:59', 'late']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('checkInTimes')]
    public function test_check_in_boundaries_ignore_forged_browser_mode_identity_and_timestamp(string $time, string $status): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-16 '.$time, 'Asia/Jakarta'));
        $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
        $this->postJson('/hr/kiosk/scan', $this->data('check_out') + ['employee_id' => 999, 'timestamp' => '2000-01-01 00:00:00'])
            ->assertOk()->assertJsonPath('type', 'check_in')->assertJsonPath('attendance_date', '2026-11-16');
        $record = AttendanceRecord::where('source', 'face_recognition')->sole();
        $this->assertSame($status, $record->status);
        $this->assertSame($time, $record->check_in_at->format('H:i:s'));
        $this->assertSame($this->employee->id, $record->employee_id);
        $this->postJson('/hr/kiosk/scan', $this->data('check_out'))->assertUnprocessable();
        $this->assertNull($record->fresh()->check_out_at);
        $this->assertSame(1, AttendanceRecord::where('source', 'face_recognition')->count());
    }

    public static function checkOutTimes(): array
    {
        return [['2026-11-16 17:00:00'], ['2026-11-16 23:59:59'], ['2026-11-17 00:00:00'], ['2026-11-17 04:59:00'], ['2026-11-17 04:59:59']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('checkOutTimes')]
    public function test_check_out_boundaries_use_correct_workday_and_preserve_status(string $instant): void
    {
        $record = AttendanceRecord::create(['employee_id' => $this->employee->id, 'attendance_date' => '2026-11-16', 'session' => 'regular',
            'check_in_at' => '2026-11-16 07:30:00', 'status' => 'present', 'source' => 'face_recognition']);
        $this->travelTo(\Carbon\Carbon::parse($instant, 'Asia/Jakarta'));
        $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
        $this->postJson('/hr/kiosk/scan', $this->data('check_in'))->assertOk()->assertJsonPath('type', 'check_out')->assertJsonPath('attendance_date', '2026-11-16');
        $this->assertSame($instant, $record->fresh()->check_out_at->format('Y-m-d H:i:s'));
        $this->assertSame('present', $record->fresh()->status);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        $this->assertSame(1, AttendanceRecord::where('source', 'face_recognition')->count());
    }

    public function test_closed_hour_does_not_invoke_ai_or_create_attendance(): void
    {
        $this->mock(FaceExtractionClient::class)->shouldNotReceive('extract');
        foreach (['05:00:00', '05:30:00', '05:59:59'] as $time) {
            $this->travelTo(\Carbon\Carbon::parse('2026-11-16 '.$time, 'Asia/Jakarta'));
            $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
            $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable()->assertJsonValidationErrors('samples');
        }
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
    }

    public function test_early_checkout_does_not_use_today_record_or_create_missing_previous_workday(): void
    {
        $record = AttendanceRecord::where('employee_id', $this->employee->id)->whereDate('attendance_date', '2026-11-17')->where('session', 'regular')->first()
            ?? new AttendanceRecord(['employee_id' => $this->employee->id, 'attendance_date' => '2026-11-17', 'session' => 'regular']);
        $record->fill(['check_in_at' => '2026-11-17 00:01:00', 'check_out_at' => null, 'status' => 'present', 'source' => 'face_recognition'])->save();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-17 01:00:00', 'Asia/Jakarta'));
        $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        $this->assertNull(AttendanceRecord::where('source', 'face_recognition')->sole()->check_out_at);
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition', 'attendance_date' => '2026-11-16 00:00:00']);
    }

    public function test_mode_is_rechecked_when_inference_crosses_into_closed_hour(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-17 04:59:59', 'Asia/Jakarta'));
        $this->withSession(['face_kiosk_expires' => now()->addMinutes(15)->timestamp]);
        $this->mock(FaceExtractionClient::class)->shouldReceive('extract')->andReturnUsing(function () {
            $this->travel(1)->seconds();
            return ['model_identifier' => 'buffalo_m:sha256:'.str_repeat('a', 64), 'embedding' => [1, ...array_fill(0, 511, 0)]];
        });
        $this->postJson('/hr/kiosk/scan', $this->data())->assertUnprocessable();
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
    }

    public function test_recognition_error_never_creates_attendance(): void
    {
        $this->mock(FaceExtractionClient::class)->shouldReceive('extract')->andThrow(new \RuntimeException('Synthetic AI outage'));
        $this->postJson('/hr/kiosk/scan', $this->data())->assertStatus(503);
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
    }

    public function test_legacy_hr_entry_redirects_to_device_management_and_old_controls_remain_protected(): void
    {
        $this->get('/hr/kiosk')->assertRedirect(route('hr.kiosk-devices.index'));
        $this->getJson('/hr/kiosk/status')->assertOk()->assertJsonPath('mode', 'check_in')->assertJsonPath('paused', false);
        $this->postJson('/hr/kiosk/stop')->assertOk()->assertJsonPath('paused', true);
        $this->get('/hr/kiosk')->assertRedirect(route('hr.kiosk-devices.index'));
        $this->postJson('/hr/kiosk/start')->assertOk()->assertJsonPath('paused', false)
            ->assertJsonPath('expires', now()->addMinutes(15)->timestamp);
        $this->actingAs(User::where('email', 'employee@facetrack.test')->firstOrFail());
        $this->getJson('/hr/kiosk/status')->assertForbidden();
        $this->postJson('/hr/kiosk/start')->assertForbidden();
    }

    public function test_kiosk_session_lock_rejects_parallel_recognition_and_releases_after_success(): void
    {
        $contended = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $contended->shouldReceive('get')->once()->andReturn(false);
        $contended->shouldNotReceive('release');
        $owned = \Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $owned->shouldReceive('get')->once()->andReturn(true);
        $owned->shouldReceive('release')->once();
        \Illuminate\Support\Facades\Cache::shouldReceive('lock')->twice()
            ->withArgs(fn ($key, $seconds) => str_starts_with($key, 'face-kiosk:') && strlen($key) === 75 && $seconds === 180)
            ->andReturn($contended, $owned);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertStatus(429)->assertHeader('Retry-After', '7');
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
        $this->postJson('/hr/kiosk/scan', $this->data())->assertOk();
    }
}

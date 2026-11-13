<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        Storage::fake('local');
        $this->seed();
        AttendanceCorrectionRequest::first()->forceFill(['attendance_date' => today()->subDay(), 'submitted_at' => today()->subDay()->setTime(8, 0)])->save();
    }

    private function user(string $role = 'employee'): User
    {
        return User::where('email', $role.'@facetrack.test')->firstOrFail();
    }

    private function correctionData(array $data = []): array
    {
        return array_replace([
            'attendance_date' => today()->subDays(2)->toDateString(), 'time' => '08:00', 'type' => 'check_in', 'reason' => 'Attendance requires HR verification.',
            'photo' => UploadedFile::fake()->image('capture.jpg', 200, 200), 'captured_at' => now()->toIso8601String(),
            'latitude' => '-6.2000000', 'longitude' => '106.8000000', 'privacy_consent' => '1',
        ], $data);
    }

    private function leaveData(array $data = []): array
    {
        return array_replace(['type' => 'sick', 'start_date' => today()->addDays(2)->toDateString(), 'end_date' => today()->addDays(3)->toDateString(), 'reason' => 'Medical leave for HR review.'], $data);
    }

    public function test_employee_routes_require_auth_and_reject_admin_hr(): void
    {
        $c = AttendanceCorrectionRequest::first();
        $l = LeaveRequest::first();
        $urls = ['/employee', '/employee/attendance', '/employee/corrections', '/employee/corrections/create', '/employee/corrections/'.$c->id, '/employee/corrections/'.$c->id.'/evidence', '/employee/leaves', '/employee/leaves/create', '/employee/leaves/'.$l->id];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        foreach (['admin', 'hr'] as $role) {
            $this->actingAs($this->user($role));
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post('/employee/corrections', $this->correctionData())->assertForbidden();
            $this->post('/employee/leaves', $this->leaveData())->assertForbidden();
        }
        $this->actingAs($this->user());
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_history_and_request_lists_are_always_scoped_to_owner(): void
    {
        $other = Employee::whereNull('user_id')->first();
        $c = AttendanceCorrectionRequest::first()->replicate();
        $c->employee_id = $other->id;
        $c->save();
        $l = LeaveRequest::first()->replicate();
        $l->employee_id = $other->id;
        $l->save();
        $this->actingAs($this->user())->get('/employee/attendance?employee_id='.$other->id)->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->employee_id === $this->user()->employee->id);
        $this->get('/employee/corrections?employee_id='.$other->id)->assertViewHas('requests', fn ($rows) => $rows->total() === 1);
        $this->get('/employee/leaves')->assertViewHas('requests', fn ($rows) => $rows->total() === 1);
        $this->get('/employee/corrections/'.$c->id)->assertForbidden();
        $this->get('/employee/corrections/'.$c->id.'/evidence')->assertForbidden();
        $this->get('/employee/leaves/'.$l->id)->assertForbidden();
        $this->get('/employee/leaves/'.$l->id.'/attachment')->assertForbidden();
    }

    public function test_correction_creates_only_pending_request_with_server_owned_fields_and_private_photo(): void
    {
        $before = AttendanceRecord::count();
        $this->actingAs($this->user())->post('/employee/corrections', $this->correctionData(['employee_id' => Employee::whereNull('user_id')->first()->id, 'status' => 'approved', 'is_demo' => true, 'submitted_at' => '2000-01-01', 'evidence_path' => 'public/fake.jpg', 'session' => 'night']))->assertRedirect();
        $r = AttendanceCorrectionRequest::latest('id')->first();
        $this->assertSame($this->user()->employee->id, $r->employee_id);
        $this->assertSame('pending', $r->status);
        $this->assertFalse($r->is_demo);
        $this->assertSame('regular', $r->session);
        $this->assertTrue($r->submitted_at->equalTo(now()));
        $this->assertStringStartsWith('corrections/'.$r->employee_id.'/', $r->evidence_path);
        Storage::disk('local')->assertExists($r->evidence_path);
        Storage::disk('public')->assertMissing($r->evidence_path);
        $this->assertSame($before, AttendanceRecord::count());
        $this->assertDatabaseCount('approval_logs', 0);
    }

    public function test_missing_photo_location_timestamp_or_consent_does_not_create_fake_data(): void
    {
        $this->actingAs($this->user());
        $this->post('/employee/corrections', $this->correctionData(['photo' => null, 'latitude' => null, 'longitude' => null, 'captured_at' => null, 'privacy_consent' => null]))->assertSessionHasErrors(['photo', 'latitude', 'longitude', 'captured_at', 'privacy_consent']);
        $this->assertDatabaseCount('attendance_correction_requests', 1);
        $this->assertSame([], Storage::disk('local')->allFiles('corrections'));
    }

    public function test_photo_mime_size_and_coordinates_are_validated(): void
    {
        $this->actingAs($this->user());
        $this->post('/employee/corrections', $this->correctionData(['photo' => UploadedFile::fake()->createWithContent('not-image.jpg', '<script>bad</script>'), 'latitude' => 91, 'longitude' => 181]))->assertSessionHasErrors(['photo', 'latitude', 'longitude']);
        $this->post('/employee/corrections', $this->correctionData(['photo' => UploadedFile::fake()->image('large.jpg')->size(6000)]))->assertSessionHasErrors('photo');
        $this->assertDatabaseCount('attendance_correction_requests', 1);
    }

    public function test_capture_timestamp_validation_and_forged_attendance_times_are_ignored(): void
    {

        $this->actingAs($this->user());
        $this->post('/employee/corrections', $this->correctionData(['captured_at' => now()->subMinutes(20)->toIso8601String()]))->assertSessionHasErrors('captured_at');
        $this->post('/employee/corrections', $this->correctionData(['captured_at' => now()->addHour()->toIso8601String()]))->assertSessionHasErrors('captured_at');
        $this->post('/employee/corrections', $this->correctionData(['attendance_date' => '2000-01-01', 'time' => '01:00', 'submitted_at' => '2000-01-01', 'requested_at' => '2000-01-01']))->assertRedirect();
        $r = AttendanceCorrectionRequest::latest('id')->first();
        $this->assertTrue($r->submitted_at->equalTo(now()));
        $this->assertTrue($r->requested_at->equalTo(now()));
        $this->assertSame(today()->toDateString(), $r->attendance_date->toDateString());

    }

    public function test_duplicate_pending_correction_is_rejected_without_extra_file(): void
    {
        $this->actingAs($this->user())->post('/employee/corrections', $this->correctionData())->assertRedirect();
        $this->post('/employee/corrections', $this->correctionData())->assertSessionHasErrors('attendance_date');
        $this->assertSame(1, count(Storage::disk('local')->allFiles('corrections')));
        $this->assertDatabaseCount('attendance_correction_requests', 2);
    }

    public function test_employee_can_submit_json_and_hr_approve_real_photo_then_owner_sees_attendance(): void
    {
        $employee = $this->user();
        $response = $this->actingAs($employee)->post('/employee/corrections', $this->correctionData(), ['Accept' => 'application/json'])->assertCreated()->assertJsonStructure(['redirect']);
        $r = AttendanceCorrectionRequest::latest('id')->first();
        $this->assertSame(route('employee.corrections.show', $r), $response->json('redirect'));
        $this->actingAs($this->user('hr'))->post('/hr/corrections/'.$r->id.'/decision', ['decision' => 'approved', 'notes' => 'Checked employee context'])->assertRedirect();
        $this->actingAs($employee)->get('/employee/corrections/'.$r->id)->assertOk()->assertSee('approved')->assertSee('Checked employee context')->assertDontSee('Simpan keputusan');
        $this->get('/employee/corrections/'.$r->id.'/evidence')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $date = $r->attendance_date->toDateString();
        $this->get('/employee/attendance?from='.$date.'&to='.$date)->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->source === 'hr_approved_correction');
    }

    public function test_unlinked_or_inactive_profile_can_read_empty_history_but_cannot_submit(): void
    {
        $employee = $this->user()->employee;
        $employee->is_active = false;
        $employee->save();
        $this->actingAs($this->user())->get('/employee')->assertSee('Profil karyawan nonaktif');
        $this->get('/employee/attendance')->assertOk();
        $this->get('/employee/corrections/create')->assertForbidden();
        $this->post('/employee/corrections', $this->correctionData())->assertForbidden();
        $this->post('/employee/leaves', $this->leaveData())->assertForbidden();
        $employee->user_id = null;
        $employee->save();
        $this->user()->unsetRelation('employee');
        $this->actingAs($this->user());
        $this->get('/employee')->assertSee('belum terhubung');
        $this->get('/employee/attendance')->assertViewHas('records', fn ($rows) => $rows->total() === 0);
        $this->get('/employee/leaves/create')->assertForbidden();
    }

    public function test_leave_upload_is_private_and_hr_decision_is_visible_to_owner(): void
    {
        $employee = $this->user();
        $this->actingAs($employee)->post('/employee/leaves', $this->leaveData(['employee_id' => 9999, 'status' => 'approved', 'attachment' => UploadedFile::fake()->createWithContent('support.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")]))->assertRedirect();
        $r = LeaveRequest::latest('id')->first();
        $this->assertSame($employee->employee->id, $r->employee_id);
        $this->assertSame('pending', $r->status);
        Storage::disk('local')->assertExists($r->attachment_path);
        $this->get('/employee/leaves/'.$r->id.'/attachment')->assertOk();
        $this->actingAs($this->user('hr'))->post('/hr/leaves/'.$r->id.'/decision', ['decision' => 'approved', 'notes' => 'Leave approved'])->assertRedirect();
        $this->actingAs($employee)->get('/employee/leaves/'.$r->id)->assertSee('Leave approved');
        $this->get('/employee/attendance?from='.$r->start_date->toDateString().'&to='.$r->end_date->toDateString())->assertViewHas('records', fn ($rows) => $rows->total() === 2 && $rows->every(fn ($r) => $r->status === 'leave'));
    }

    public function test_leave_without_attachment_and_validation_conflict_rules(): void
    {
        $this->actingAs($this->user())->post('/employee/leaves', $this->leaveData())->assertRedirect();
        $this->assertNull(LeaveRequest::latest('id')->first()->attachment_path);
        $this->post('/employee/leaves', $this->leaveData())->assertSessionHasErrors('start_date');
        $this->post('/employee/leaves', $this->leaveData(['start_date' => '2026-11-20', 'end_date' => '2026-11-19']))->assertSessionHasErrors('end_date');
        $this->post('/employee/leaves', $this->leaveData(['end_date' => today()->addDays(400)->toDateString()]))->assertSessionHasErrors('end_date');
        $this->post('/employee/leaves', $this->leaveData(['type' => 'invalid', 'attachment' => UploadedFile::fake()->createWithContent('evil.html', '<script>alert(1)</script>')]))->assertSessionHasErrors(['type', 'attachment']);
        $this->post('/employee/leaves', $this->leaveData(['attachment' => UploadedFile::fake()->create('large.pdf', 6000, 'application/pdf')]))->assertSessionHasErrors('attachment');
        $yesterday = today()->subDay()->toDateString();
        $this->post('/employee/leaves', $this->leaveData(['start_date' => $yesterday, 'end_date' => $yesterday]))->assertSessionHasErrors('start_date');
    }

    public function test_approved_leave_blocks_new_correction_and_request_filtering_works(): void
    {
        $r = LeaveRequest::first();
        $r->start_date = today();
        $r->end_date = today();
        $r->status = 'approved';
        $r->save();
        $this->actingAs($this->user())->post('/employee/corrections', $this->correctionData(['attendance_date' => $r->start_date->toDateString()]))->assertSessionHasErrors('attendance_date');
        $this->get('/employee/leaves?status=approved')->assertViewHas('requests', fn ($rows) => $rows->total() === 1);
        $this->get('/employee/corrections?status=rejected')->assertViewHas('requests', fn ($rows) => $rows->total() === 0);
        $this->get('/employee/attendance?from=2026-11-11&to=2026-01-01')->assertSessionHasErrors('to');
    }

    public function test_employee_has_no_decision_edit_delete_or_attendance_write_endpoint(): void
    {
        $this->actingAs($this->user());
        $c = AttendanceCorrectionRequest::first();
        $this->post('/employee/corrections/'.$c->id.'/decision', ['decision' => 'approved'])->assertNotFound();
        $this->put('/employee/corrections/'.$c->id, [])->assertStatus(405);
        $this->delete('/employee/corrections/'.$c->id)->assertStatus(405);
        $this->post('/employee/attendance', [])->assertStatus(405);
        $this->assertDatabaseCount('approval_logs', 0);
    }

    public function test_failed_database_save_cleans_up_uploaded_evidence(): void
    {
        AttendanceCorrectionRequest::creating(fn () => throw new \RuntimeException('Simulated DB write failure'));
        $this->actingAs($this->user())->post('/employee/corrections', $this->correctionData())->assertStatus(500);
        $this->assertSame([], Storage::disk('local')->allFiles('corrections'));
        $this->assertDatabaseCount('attendance_correction_requests', 1);
    }

    public function test_capture_timestamp_from_browser_utc_is_stored_in_jakarta_time(): void
    {
        $this->actingAs($this->user())->post('/employee/corrections', $this->correctionData(['captured_at' => now()->utc()->toIso8601String()]))->assertRedirect();
        $r = AttendanceCorrectionRequest::latest('id')->first();
        $this->assertSame('12:00:00', $r->captured_at->format('H:i:s'));
        $this->assertSame('12:00:00', $r->requested_at->format('H:i:s'));
    }

    public function test_owner_pagination_preserves_filters_and_no_attachment_is_404(): void
    {
        $owner = $this->user()->employee;
        for ($i = 0; $i < 17; $i++) {
            $r = AttendanceCorrectionRequest::first()->replicate();
            $r->employee_id = $owner->id;
            $r->save();
        }
        $this->actingAs($this->user())->get('/employee/corrections?status=pending&page=2')->assertOk()->assertViewHas('requests', fn ($rows) => $rows->total() === 18 && $rows->currentPage() === 2)->assertSee('status=pending', false);
        $this->get('/employee/leaves/'.LeaveRequest::first()->id.'/attachment')->assertNotFound();
    }
}

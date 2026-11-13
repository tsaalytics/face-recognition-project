<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Division;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HrModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        Storage::fake('local');
        $this->seed();
        AttendanceCorrectionRequest::first()->forceFill(['submitted_at' => today()->setTime(8, 0)])->save();
    }

    private function user(string $role = 'hr'): User
    {
        return User::where('email', $role.'@facetrack.test')->firstOrFail();
    }

    private function correction(): AttendanceCorrectionRequest
    {
        return AttendanceCorrectionRequest::firstOrFail();
    }

    private function payload(array $data = []): array
    {
        return array_replace(['date_of_birth' => '2000-05-17', 'home_address' => 'Alamat sintetis pengujian', 'employee_number' => 'HR-NEW', 'name' => 'New Employee', 'email' => 'newprofile@facetrack.test', 'phone' => '08000000', 'position' => 'Operator', 'division_id' => Division::first()->id, 'is_active' => '1', 'user_id' => null], $data);
    }

    private function decide(string $decision = 'approved', array $data = [])
    {
        return $this->actingAs($this->user())->post('/hr/corrections/'.$this->correction()->id.'/decision', array_replace(['decision' => $decision, 'notes' => 'Reviewed'], $data));
    }

    public function test_hr_pages_and_all_routes_deny_other_roles_and_guests(): void
    {
        $id = $this->correction()->id;
        $leave = LeaveRequest::first()->id;
        $employee = Employee::first()->id;
        $urls = ['/hr', '/hr/employees', '/hr/employees/create', '/hr/employees/'.$employee.'/edit', '/hr/attendance', '/hr/attendance/export', '/hr/enrollment', '/hr/corrections', '/hr/corrections/'.$id, '/hr/corrections/'.$id.'/evidence', '/hr/leaves', '/hr/leaves/'.$leave];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        foreach (['admin', 'employee'] as $role) {
            $this->actingAs($this->user($role));
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post('/hr/corrections/'.$id.'/decision', ['decision' => 'approved'])->assertForbidden();
            $this->post('/hr/leaves/'.$leave.'/decision', ['decision' => 'approved'])->assertForbidden();
            $this->post('/hr/employees', $this->payload())->assertForbidden();
            $this->put('/hr/employees/'.$employee, $this->payload())->assertForbidden();
        }
        $this->actingAs($this->user());
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_hr_creates_edits_links_and_unlinks_profiles_without_creating_accounts(): void
    {
        $this->actingAs($this->user())->post('/hr/employees', $this->payload())->assertRedirect('/hr/employees');
        $new = Employee::where('employee_number', 'HR-NEW')->firstOrFail();
        $this->assertDatabaseCount('users', 3);
        $owner = $this->user('employee');
        $owner->employee->update(['user_id' => null]);
        $this->put('/hr/employees/'.$new->id, $this->payload(['user_id' => $owner->id, 'email' => $owner->email, 'name' => 'Edited']))->assertRedirect('/hr/employees');
        $this->assertSame($owner->id, $new->fresh()->user_id);
        $this->assertSame('Edited', $new->fresh()->name);
        $this->put('/hr/employees/'.$new->id, $this->payload(['user_id' => null, 'email' => $owner->email, 'is_active' => '0']))->assertRedirect('/hr/employees');
        $this->assertNull($new->fresh()->user_id);
        $this->assertFalse($new->fresh()->is_active);
        $this->get('/hr/employees')->assertSee('Edit profil');
    }

    public function test_profile_validation_blocks_invalid_or_duplicate_account_links_and_enrollment_forgery(): void
    {
        $this->actingAs($this->user());
        foreach ([$this->user('admin')->id, $this->user('employee')->id] as $id) {
            $this->post('/hr/employees', $this->payload(['user_id' => $id]))->assertSessionHasErrors('user_id');
        }
        $this->post('/hr/employees', $this->payload(['employee_number' => 'DEMO-001', 'email' => 'demo1@facetrack.test', 'division_id' => 99999]))->assertSessionHasErrors(['employee_number', 'email', 'division_id']);
        $this->post('/hr/employees', $this->payload(['enrollment_status' => 'enrolled']))->assertRedirect('/hr/employees');
        $this->assertDatabaseHas('employees', ['employee_number' => 'HR-NEW', 'enrollment_status' => 'not_enrolled']);
    }

    public function test_correction_approval_creates_attendance_and_immutable_decision(): void
    {
        $request = $this->correction();
        $this->decide()->assertRedirect('/hr/corrections/'.$request->id);
        $this->assertSame('approved', $request->fresh()->status);
        $record = AttendanceRecord::whereDate('attendance_date', today())->firstOrFail();
        $this->assertSame('hr_approved_correction', $record->source);
        $this->assertSame('present', $record->status);
        $this->assertSame('08:00', $record->check_in_at->format('H:i'));
        $this->assertDatabaseHas('approval_logs', ['attendance_correction_request_id' => $request->id, 'leave_request_id' => null, 'reviewed_by' => $this->user()->id, 'from_status' => 'pending', 'to_status' => 'approved', 'notes' => 'Reviewed']);
        $this->decide()->assertForbidden();
        $this->assertDatabaseCount('approval_logs', 1);
        $log = ApprovalLog::first();
        $this->expectException(\LogicException::class);
        $log->notes = 'changed';
        $log->save();
    }

    public function test_rejection_preserves_attendance_and_requires_notes(): void
    {
        $before = AttendanceRecord::count();
        $this->decide('rejected', ['notes' => ''])->assertSessionHasErrors('notes');
        $this->decide('rejected', ['notes' => 'Evidence insufficient'])->assertRedirect();
        $this->assertSame($before, AttendanceRecord::count());
        $this->assertSame('rejected', $this->correction()->status);
        $this->assertDatabaseHas('approval_logs', ['to_status' => 'rejected', 'notes' => 'Evidence insufficient']);
    }

    public function test_check_out_requires_check_in_and_failure_rolls_back_everything(): void
    {
        $r = $this->correction();
        $r->type = 'check_out';
        $r->save();
        $this->decide()->assertSessionHasErrors('decision');
        $this->assertSame('pending', $r->fresh()->status);
        $this->assertDatabaseCount('approval_logs', 0);
        $this->assertSame(0, AttendanceRecord::whereDate('attendance_date', today())->count());
    }

    public function test_approval_updates_existing_session_and_calculates_lateness(): void
    {
        $r = $this->correction();
        $r->submitted_at = today()->setTime(8, 15);
        $r->save();
        AttendanceRecord::create(['employee_id' => $r->employee_id, 'attendance_date' => today(), 'session' => 'regular', 'status' => 'absent', 'source' => 'seed_fixture']);
        $this->decide()->assertRedirect();
        $this->assertSame(1, AttendanceRecord::whereDate('attendance_date', today())->count());
        $record = AttendanceRecord::whereDate('attendance_date', today())->firstOrFail();
        $this->assertSame('late', $record->status);
        $out = $r->replicate();
        $out->type = 'check_out';
        $out->status = 'pending';
        $out->submitted_at = today()->setTime(11, 30);
        $out->save();
        $this->actingAs($this->user())->post('/hr/corrections/'.$out->id.'/decision', ['decision' => 'approved'])->assertRedirect();
        $this->assertSame('11:30', $record->fresh()->check_out_at->format('H:i'));
        $this->assertDatabaseCount('approval_logs', 2);
    }

    public function test_correction_time_and_session_rules_are_enforced(): void
    {
        $r = $this->correction();
        foreach ([['submitted_at' => today()->addDay()->setTime(8, 0)], ['submitted_at' => now()->addHour()], ['submitted_at' => today()->setTime(8, 0), 'session' => 'night']] as $data) {
            $r->forceFill($data)->save();
            $this->decide()->assertSessionHasErrors('decision');
        }
        $this->assertDatabaseCount('approval_logs', 0);
    }

    public function test_checkout_before_checkin_or_checkin_after_checkout_is_denied(): void
    {
        $r = $this->correction();
        AttendanceRecord::create(['employee_id' => $r->employee_id, 'attendance_date' => today(), 'session' => 'regular', 'check_in_at' => today()->setTime(9, 0), 'check_out_at' => today()->setTime(10, 0), 'status' => 'late', 'source' => 'seed_fixture']);
        $r->type = 'check_out';
        $r->submitted_at = today()->setTime(8, 0);
        $r->save();
        $this->decide()->assertSessionHasErrors('decision');
        $r->type = 'check_in';
        $r->submitted_at = today()->setTime(11, 0);
        $r->save();
        $this->decide()->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('approval_logs', 0);
    }

    public function test_real_evidence_is_required_and_private_files_are_authorized(): void
    {
        $r = $this->correction();
        $r->is_demo = false;
        $r->evidence_path = 'corrections/missing.png';
        $r->save();
        $this->decide()->assertSessionHasErrors('decision');
        Storage::disk('local')->put('corrections/proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $r->evidence_path = 'corrections/proof.png';
        $r->save();
        $this->actingAs($this->user())->get('/hr/corrections/'.$r->id.'/evidence')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->user('employee'))->get('/hr/corrections/'.$r->id.'/evidence')->assertForbidden();
        $this->decide()->assertRedirect();
    }

    public function test_path_traversal_and_invalid_file_mime_are_denied(): void
    {
        $r = $this->correction();
        $r->evidence_path = 'demo/../../.env';
        $r->save();
        $this->actingAs($this->user())->get('/hr/corrections/'.$r->id.'/evidence')->assertNotFound();
        Storage::disk('local')->put('corrections/bad.html', '<script>alert(1)</script>');
        $r->is_demo = false;
        $r->evidence_path = 'corrections/bad.html';
        $r->save();
        $this->get('/hr/corrections/'.$r->id.'/evidence')->assertNotFound();
        $this->decide()->assertSessionHasErrors('decision');
    }

    public function test_approved_leave_generates_leave_days_and_a_single_log(): void
    {
        $leave = LeaveRequest::first();
        $this->actingAs($this->user())->post('/hr/leaves/'.$leave->id.'/decision', ['decision' => 'approved', 'notes' => 'Valid leave'])->assertRedirect();
        $this->assertSame('approved', $leave->fresh()->status);
        $this->assertSame(2, AttendanceRecord::where('status', 'leave')->count());
        $this->assertDatabaseHas('approval_logs', ['leave_request_id' => $leave->id, 'attendance_correction_request_id' => null, 'to_status' => 'approved']);
        $this->post('/hr/leaves/'.$leave->id.'/decision', ['decision' => 'rejected', 'notes' => 'Again'])->assertForbidden();
    }

    public function test_leave_conflict_with_presence_and_overlap_rolls_back(): void
    {
        $leave = LeaveRequest::first();
        AttendanceRecord::create(['employee_id' => $leave->employee_id, 'attendance_date' => $leave->start_date, 'session' => 'regular', 'check_in_at' => $leave->start_date->copy()->setTime(8, 0), 'status' => 'present', 'source' => 'seed_fixture']);
        $this->actingAs($this->user())->post('/hr/leaves/'.$leave->id.'/decision', ['decision' => 'approved'])->assertSessionHasErrors('decision');
        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertDatabaseCount('approval_logs', 0);
        $other = $leave->replicate();
        $other->status = 'approved';
        $other->save();
        $this->post('/hr/leaves/'.$leave->id.'/decision', ['decision' => 'approved'])->assertSessionHasErrors('decision');
    }

    public function test_approved_leave_blocks_correction_and_inactive_employee_cannot_be_approved(): void
    {
        $r = $this->correction();
        $leave = LeaveRequest::first();
        $leave->start_date = today();
        $leave->end_date = today();
        $leave->status = 'approved';
        $leave->save();
        $this->decide()->assertSessionHasErrors('decision');
        $r->employee->update(['is_active' => false]);
        $this->decide()->assertSessionHasErrors('decision');
        $this->decide('rejected')->assertRedirect();
    }

    public function test_reject_leave_and_private_attachment_download(): void
    {
        $leave = LeaveRequest::first();
        Storage::disk('local')->put('leaves/doc.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF");
        $leave->attachment_path = 'leaves/doc.pdf';
        $leave->save();
        $this->actingAs($this->user())->get('/hr/leaves/'.$leave->id.'/attachment')->assertOk();
        $this->actingAs($this->user('admin'))->get('/hr/leaves/'.$leave->id.'/attachment')->assertForbidden();
        $this->actingAs($this->user())->post('/hr/leaves/'.$leave->id.'/decision', ['decision' => 'rejected', 'notes' => 'Incomplete'])->assertRedirect();
        $this->assertSame('rejected', $leave->fresh()->status);
        $this->assertSame(0, AttendanceRecord::where('status', 'leave')->count());
    }

    public function test_csv_export_applies_filters_all_pages_and_escapes_formula_cells(): void
    {
        $employee = Employee::first();
        $employee->name = '=HYPERLINK("bad")';
        $employee->save();
        for ($i = 1; $i <= 20; $i++) {
            AttendanceRecord::create(['employee_id' => $employee->id, 'attendance_date' => today()->subDays($i + 1), 'session' => 'regular', 'check_in_at' => today()->subDays($i + 1)->setTime(8, 0), 'status' => 'present', 'source' => 'seed_fixture']);
        }
        $this->actingAs($this->user());
        $csv = $this->get('/hr/attendance/export?q=DEMO-001')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertSame(22, count(array_filter(explode("\n", trim($csv)))));
        $filtered = $this->get('/hr/attendance/export?status=late')->assertOk()->streamedContent();
        $this->assertStringNotContainsString('HYPERLINK', $filtered);
        $this->assertStringContainsString('Data demo sintetis', $filtered);
    }

    public function test_dashboard_counts_pending_divisions_trend_and_unique_today(): void
    {
        $r = $this->correction();
        $this->decide();
        $this->get('/hr')->assertOk()->assertViewHasAll(['active' => 6, 'present' => 1, 'late' => 0, 'absent' => 5, 'pendingCorrections' => 0, 'pendingLeaves' => 1])->assertViewHas('trend', fn ($t) => count($t) === 7 && $t[6]['count'] === 1)->assertViewHas('divisions', fn ($d) => $d->sum('present_count') === 1);
        $this->get('/hr/corrections?status=approved&q=DEMO-001')->assertViewHas('requests', fn ($r) => $r->total() === 1);
        $this->get('/hr/leaves?status=rejected')->assertSee('Tidak ada permohonan yang cocok');
        $this->get('/hr/enrollment')->assertSee('AI belum diaktifkan')->assertSee('Not Enrolled');
        $this->post('/hr/enrollment', [])->assertStatus(405);
    }

    public function test_hr_cannot_approve_own_request(): void
    {
        $employee = $this->correction()->employee;
        $employee->user_id = $this->user()->id;
        $employee->save();
        $this->decide()->assertForbidden();
        $this->assertDatabaseCount('approval_logs', 0);
    }

    public function test_inactive_existing_login_link_can_be_retained_but_not_newly_assigned(): void
    {
        $owner = $this->user('employee');
        $owner->is_active = false;
        $owner->save();
        $employee = $owner->employee;
        $this->actingAs($this->user())->put('/hr/employees/'.$employee->id, $this->payload(['employee_number' => $employee->employee_number, 'email' => $employee->email, 'user_id' => $owner->id]))->assertRedirect('/hr/employees');
        $this->assertSame($owner->id, $employee->fresh()->user_id);
        $employee->update(['user_id' => null]);
        $this->post('/hr/employees', $this->payload(['user_id' => $owner->id]))->assertSessionHasErrors('user_id');
    }

    public function test_invalid_leave_ranges_are_rejected_without_attendance_or_logs(): void
    {
        $leave = LeaveRequest::first();
        $leave->end_date = $leave->start_date->copy()->subDay();
        $leave->save();
        $this->actingAs($this->user())->post('/hr/leaves/'.$leave->id.'/decision', ['decision' => 'approved'])->assertSessionHasErrors('decision');
        $leave->end_date = $leave->start_date->copy()->addDays(400);
        $leave->save();
        $this->post('/hr/leaves/'.$leave->id.'/decision', ['decision' => 'approved'])->assertSessionHasErrors('decision');
        $this->assertSame('pending', $leave->fresh()->status);
        $this->assertDatabaseCount('approval_logs', 0);
        $this->assertDatabaseCount('attendance_records', 6);
    }

    public function test_oversized_photo_and_invalid_coordinates_block_approval(): void
    {
        $r = $this->correction();
        $r->latitude = 91;
        $r->save();
        $this->decide()->assertSessionHasErrors('decision');
        $r->latitude = 0;
        $r->is_demo = false;
        $r->evidence_path = 'corrections/large.png';
        $r->save();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        Storage::disk('local')->put('corrections/large.png', $png.str_repeat('x', 6 * 1024 * 1024));
        $this->decide()->assertSessionHasErrors('decision');
        $this->assertDatabaseCount('approval_logs', 0);
    }
}

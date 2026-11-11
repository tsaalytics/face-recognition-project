<?php

namespace Tests\Feature;

use App\Contracts\FaceRecognitionService;
use App\Enums\Role;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function account(string $role): User
    {
        return User::where('email', $role.'@facetrack.test')->firstOrFail();
    }

    public function test_login_page_is_accessible_and_registration_is_absent(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke workspace');
        $this->get('/register')->assertNotFound();
    }

    public function test_every_role_can_login_and_logout(): void
    {
        foreach (['admin', 'hr', 'employee'] as $role) {
            $this->post('/login', ['email' => $role.'@facetrack.test', 'password' => 'DemoFaceTrack!2026'])->assertRedirect('/');
            $this->assertAuthenticatedAs($this->account($role));
            $this->get('/')->assertRedirect('/'.$role);
            $this->get('/'.$role)->assertOk()->assertSee('AI belum diaktifkan');
            $this->post('/logout')->assertRedirect('/login');
            $this->assertGuest();
        }
    }

    public function test_role_route_matrix_denies_all_cross_role_access(): void
    {
        foreach (['admin', 'hr', 'employee'] as $role) {
            $this->actingAs($this->account($role));
            foreach (['admin', 'hr', 'employee'] as $target) {
                $this->get('/'.$target)->assertStatus($role === $target ? 200 : 403);
            }
        }
    }

    public function test_all_application_routes_require_authentication(): void
    {
        foreach (['/', '/admin', '/hr', '/employee'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_invalid_and_inactive_credentials_are_rejected(): void
    {
        $this->post('/login', ['email' => 'admin@facetrack.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $user = $this->account('admin');
        $user->is_active = false;
        $user->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'DemoFaceTrack!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_disabled_session_is_revoked(): void
    {
        $user = $this->account('employee');
        $this->actingAs($user);
        $user->is_active = false;
        $user->save();
        $this->get('/employee')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('admin@facetrack.test|127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'admin@facetrack.test', 'password' => 'wrong']);
        }
        $this->post('/login', ['email' => 'admin@facetrack.test', 'password' => 'DemoFaceTrack!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
        RateLimiter::clear('admin@facetrack.test|127.0.0.1');
    }

    public function test_employee_cannot_view_other_employee_records(): void
    {
        $user = $this->account('employee');
        $other = Employee::whereNull('user_id')->first();
        $this->assertTrue(Gate::forUser($user)->allows('view', $user->employee));
        $this->assertFalse(Gate::forUser($user)->allows('view', $other));
        $this->assertFalse(Gate::forUser($user)->allows('view', $other->attendanceRecords()->first()));
        $this->assertTrue(Gate::forUser($user)->allows('view', AttendanceCorrectionRequest::first()));
    }

    public function test_only_hr_can_approve_pending_requests_and_admin_cannot_mutate_attendance(): void
    {
        foreach ([AttendanceCorrectionRequest::first(), LeaveRequest::first()] as $request) {
            foreach (['admin', 'employee'] as $role) {
                $this->assertFalse(Gate::forUser($this->account($role))->allows('approve', $request));
                $this->assertFalse(Gate::forUser($this->account($role))->allows('reject', $request));
            }
            $this->assertTrue(Gate::forUser($this->account('hr'))->allows('approve', $request));
            $request->status = 'approved';
            $this->assertFalse(Gate::forUser($this->account('hr'))->allows('approve', $request));
        }
        $admin = $this->account('admin');
        $record = AttendanceRecord::first();
        foreach (['update', 'delete'] as $action) {
            $this->assertFalse(Gate::forUser($admin)->allows($action, $record));
        }
        $this->assertFalse(Gate::forUser($admin)->allows('create', AttendanceRecord::class));
    }

    public function test_mass_assignment_cannot_escalate_role_or_request_status(): void
    {
        $user = $this->account('employee');
        $user->fill(['role' => 'admin', 'is_active' => false]);
        $this->assertSame(Role::Employee, $user->role);
        $this->assertTrue($user->is_active);
        $request = AttendanceCorrectionRequest::first();
        $request->fill(['status' => 'approved', 'is_demo' => false]);
        $this->assertSame('pending', $request->status);
        $this->assertTrue($request->is_demo);
    }

    public function test_database_rejects_duplicate_attendance_session(): void
    {
        $record = AttendanceRecord::first();
        $copy = $record->replicate();
        $this->expectException(QueryException::class);
        $copy->save();
    }

    public function test_seeding_is_repeatable_and_never_claims_ai_results(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('employees', 6);
        $this->assertDatabaseCount('attendance_records', 6);
        $this->assertDatabaseCount('attendance_correction_requests', 1);
        $this->assertSame(0, AttendanceRecord::where('source', '!=', 'seed_fixture')->count());
        $this->assertSame(0, Employee::where('enrollment_status', 'enrolled')->count());
        $service = app(FaceRecognitionService::class);
        $this->assertFalse($service->isAvailable());
        $this->expectException(\LogicException::class);
        $service->verify('unused');
    }
}

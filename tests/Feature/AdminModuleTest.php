<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\AccountInvitation;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        Mail::fake();
    }

    private function account(string $role = 'admin'): User
    {
        return User::where('email', $role.'@facetrack.test')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['employee_id' => Employee::whereNull('user_id')->first()->id, 'role' => 'hr', 'account_status' => 'active'], $overrides);
    }

    public function test_admin_pages_are_accessible_only_to_admin(): void
    {
        $urls = ['/admin', '/admin/users', '/admin/users/create', '/admin/employees', '/admin/attendance', '/admin/users/'.$this->account('hr')->id.'/edit'];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        foreach (['hr', 'employee'] as $role) {
            $this->actingAs($this->account($role));
            foreach ($urls as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->post('/admin/users', $this->payload())->assertForbidden();
            $this->put('/admin/users/'.$this->account('hr')->id, $this->payload())->assertForbidden();
        }
        $this->actingAs($this->account());
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_invites_hr_and_employee_using_profile_email(): void
    {

        $this->actingAs($this->account());
        foreach (['hr', 'employee'] as $role) {
            $profile = Employee::whereNull('user_id')->first();
            $this->post('/admin/users', $this->payload(['employee_id' => $profile->id, 'role' => $role, 'email' => 'forged@facetrack.test']))->assertRedirect('/admin/users');
            $user = $profile->fresh()->user;
            $this->assertSame($profile->email, $user->email);
            $this->assertSame($role, $user->role->value);
            $this->assertSame('pending_activation', $user->account_status);
            $this->assertFalse($user->is_active);
        }
        Mail::assertSent(AccountInvitation::class, 2);

    }

    public function test_invalid_role_profile_and_manual_password_are_rejected(): void
    {

        $this->actingAs($this->account());
        $this->post('/admin/users', $this->payload(['role' => 'admin', 'employee_id' => 99999, 'password' => 'manual']))->assertSessionHasErrors(['role', 'employee_id', 'password']);
        $this->post('/admin/users', $this->payload(['employee_id' => $this->account('employee')->employee->id]))->assertSessionHasErrors('employee_id');
        $this->assertDatabaseCount('users', 3);

    }

    public function test_admin_accounts_cannot_be_edited_or_disabled(): void
    {
        $admin = $this->account();
        $this->actingAs($admin);
        $this->get('/admin/users/'.$admin->id.'/edit')->assertForbidden();
        $this->put('/admin/users/'.$admin->id, $this->payload(['role' => 'employee', 'is_active' => '0']))->assertForbidden();
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }

    public function test_admin_can_edit_role_and_disable_account_without_changing_password(): void
    {

        $hr = $this->account('hr');
        $password = $hr->password;
        $this->actingAs($this->account())->put('/admin/users/'.$hr->id, ['role' => 'employee', 'account_status' => 'disabled'])->assertRedirect('/admin/users');
        $this->assertSame(Role::Employee, $hr->fresh()->role);
        $this->assertFalse($hr->fresh()->is_active);
        $this->assertSame($password, $hr->fresh()->password);

    }

    public function test_linked_profile_can_receive_hr_role_without_losing_relationship(): void
    {

        $user = $this->account('employee');
        $this->actingAs($this->account())->put('/admin/users/'.$user->id, ['role' => 'hr', 'account_status' => 'active'])->assertRedirect('/admin/users');
        $this->assertSame(Role::Hr, $user->fresh()->role);
        $this->assertNotNull($user->fresh()->employee);

    }

    public function test_disabling_revokes_sessions_and_admin_cannot_reset_password(): void
    {

        $user = $this->account('hr');
        $password = $user->password;
        $user->remember_token = 'old-token';
        $user->save();
        DB::table('sessions')->insert(['id' => 'target-session', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()]);
        config(['session.driver' => 'database']);
        $this->actingAs($this->account())->put('/admin/users/'.$user->id, ['role' => 'hr', 'account_status' => 'disabled'])->assertRedirect('/admin/users');
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->assertSame($password, $user->fresh()->password);
        $this->put('/admin/users/'.$user->id, ['role' => 'hr', 'account_status' => 'disabled', 'password' => 'SecureExample!2026'])->assertSessionHasErrors('password');

    }

    public function test_user_search_role_status_and_pagination(): void
    {
        $this->actingAs($this->account());
        $this->get('/admin/users?q=Demo&role=hr&active=1')->assertOk()->assertViewHas('users', fn ($users) => $users->total() === 1 && $users->first()->role === Role::Hr);
        for ($i = 0; $i < 17; $i++) {
            $u = new User(['name' => 'Extra '.sprintf('%02d', $i), 'email' => 'extra'.$i.'@facetrack.test', 'password' => 'SecureExample!2026']);
            $u->role = Role::Hr;
            $u->save();
        }
        $this->get('/admin/users?role=hr&page=2')->assertOk()->assertViewHas('users', fn ($users) => $users->currentPage() === 2 && $users->total() === 18)->assertSee('role=hr', false);
    }

    public function test_employee_filters_and_empty_states(): void
    {
        $employee = Employee::where('employee_number', 'DEMO-001')->firstOrFail();
        $this->actingAs($this->account())->get('/admin/employees?q=DEMO-001&division_id='.$employee->division_id.'&active=1')->assertOk()->assertViewHas('employees', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $employee->id);
        $this->get('/admin/employees?q=NoMatchingEmployee')->assertOk()->assertSee('Tidak ada karyawan yang cocok');
        $employee->is_active = false;
        $employee->save();
        $this->get('/admin/employees?active=0')->assertOk()->assertViewHas('employees', fn ($rows) => $rows->total() === 1);
    }

    public function test_attendance_filters_date_name_division_and_status(): void
    {
        $employee = Employee::where('employee_number', 'DEMO-002')->firstOrFail();
        $date = today()->subDay()->toDateString();
        $this->actingAs($this->account())->get('/admin/attendance?'.http_build_query(['q' => 'DEMO-002', 'division_id' => $employee->division_id, 'from' => $date, 'to' => $date, 'status' => 'late']))->assertOk()->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->first()->employee_id === $employee->id)->assertSee('Data demo sintetis')->assertSee('Akses baca saja');
        $this->get('/admin/attendance?from='.today()->toDateString())->assertOk()->assertSee('Tidak ada catatan absensi');
        $this->get('/admin/attendance?from=2026-11-11&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/admin/attendance?division_id=999999&status=invalid&from=not-a-date')->assertSessionHasErrors(['division_id', 'status', 'from']);
    }

    public function test_dashboard_deduplicates_sessions_excludes_inactive_and_other_dates(): void
    {
        $employees = Employee::orderBy('id')->get();
        $employees[2]->update(['is_active' => false]);
        foreach ([[$employees[0], 'regular', 'present'], [$employees[0], 'evening', 'late'], [$employees[1], 'regular', 'late'], [$employees[2], 'regular', 'present']] as [$employee,$session,$status]) {
            AttendanceRecord::create(['employee_id' => $employee->id, 'attendance_date' => today()->toDateString(), 'session' => $session, 'check_in_at' => today()->setTime(8, 0), 'status' => $status, 'source' => 'seed_fixture']);
        }
        $this->actingAs($this->account())->get('/admin')->assertOk()->assertViewHasAll(['employeeCount' => 6, 'activeEmployeeCount' => 5, 'presentCount' => 2, 'absentCount' => 3, 'userCount' => 3]);
    }

    public function test_monitoring_has_no_mutation_endpoints_and_no_user_delete(): void
    {
        $this->actingAs($this->account());
        $before = AttendanceRecord::count();
        $id = AttendanceRecord::first()->id;
        $this->post('/admin/attendance', [])->assertStatus(405);
        $this->put('/admin/attendance/'.$id, [])->assertNotFound();
        $this->delete('/admin/attendance/'.$id)->assertNotFound();
        $this->post('/admin/attendance/'.$id.'/approve')->assertNotFound();
        $this->post('/admin/employees', [])->assertStatus(405);
        $this->delete('/admin/users/'.$this->account('hr')->id)->assertStatus(405);
        $this->assertSame($before, AttendanceRecord::count());
    }
}

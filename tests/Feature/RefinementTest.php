<?php

namespace Tests\Feature;

use App\Mail\AccountInvitation;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeIdGenerator;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RefinementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00'));
        Storage::fake('local');
        $this->seed();
        Mail::fake();
    }

    private function user(string $role = 'admin'): User
    {
        return User::where('email', $role.'@facetrack.test')->firstOrFail();
    }

    private function profileData(array $data = []): array
    {
        return array_replace(['employee_number' => 'NIK-NEW', 'name' => 'Synthetic Person', 'date_of_birth' => '2000-05-17', 'home_address' => 'Alamat sintetis untuk pengujian', 'division_id' => 1, 'email' => 'synthetic@facetrack.test', 'phone' => '00123456789', 'position' => 'Operator', 'is_active' => 1], $data);
    }

    private function invite(): User
    {
        $employee = Employee::whereNull('user_id')->first();
        $this->actingAs($this->user())->post('/admin/users', ['employee_id' => $employee->id, 'role' => 'employee', 'email' => 'forged@facetrack.test'])->assertRedirect('/admin/users');

        return $employee->fresh()->user;
    }

    private function token(): string
    {
        $mail = Mail::sent(AccountInvitation::class)->last();

        return rawurldecode(basename(parse_url($mail->activationUrl, PHP_URL_PATH)));
    }

    private function activation(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'PrivateExample!2026', 'password_confirmation' => 'PrivateExample!2026'];
    }

    public function test_employee_id_format_leading_zero_collision_retry_and_immutable_on_birth_date_change(): void
    {
        $existing = Employee::first();
        $existing->business_id = '000120000517';
        $existing->save();
        $generator = new class extends EmployeeIdGenerator
        {
            private int $i = 0;

            public function randomPrefix(): string
            {
                return ++$this->i === 1 ? '0001' : '0002';
            }
        };
        $this->app->instance(EmployeeIdGenerator::class, $generator);
        $this->actingAs($this->user('hr'))->post('/hr/employees', $this->profileData(['business_id' => '999999999999']))->assertRedirect('/hr/employees');
        $new = Employee::where('employee_number', 'NIK-NEW')->firstOrFail();
        $id = $new->business_id;
        $this->assertSame('000220000517', $id);
        $this->assertMatchesRegularExpression('/^[0-9]{12}$/', $id);
        $this->assertSame('00123456789', $new->phone);
        $this->put('/hr/employees/'.$new->id, $this->profileData(['date_of_birth' => '2001-01-02', 'business_id' => 'forged']))->assertRedirect('/hr/employees');
        $this->assertSame($id, $new->fresh()->business_id);
    }

    public function test_profile_required_fields_nik_email_uniqueness_and_invalid_dates(): void
    {
        $this->actingAs($this->user('hr'))->post('/hr/employees', $this->profileData(['employee_number' => 'DEMO-001', 'email' => 'demo1@facetrack.test', 'phone' => '', 'date_of_birth' => 'future', 'home_address' => '']))->assertSessionHasErrors(['employee_number', 'email', 'phone', 'date_of_birth', 'home_address']);
    }

    public function test_id_database_constraint_and_backfill_skip_unknown_birthdates(): void
    {
        $employee = Employee::whereNull('user_id')->first();
        $employee->business_id = null;
        $employee->save();
        $missing = Employee::whereKeyNot($employee->id)->whereNull('user_id')->first();
        $missing->business_id = null;
        $missing->date_of_birth = null;
        $missing->save();
        $this->artisan('employees:backfill-ids', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($employee->fresh()->business_id);
        $this->artisan('employees:backfill-ids')->assertSuccessful();
        $id = $employee->fresh()->business_id;
        $this->assertNotNull($id);
        $this->assertNull($missing->fresh()->business_id);
        $this->artisan('employees:backfill-ids')->assertSuccessful();
        $this->assertSame($id, $employee->fresh()->business_id);
        $this->expectException(UniqueConstraintViolationException::class);
        $missing->business_id = $id;
        $missing->save();
    }

    public function test_invitation_email_token_hash_activation_once_and_login(): void
    {
        $user = $this->invite();
        $token = $this->token();
        Mail::assertSent(AccountInvitation::class, fn ($mail) => $mail->hasTo($user->email) && $mail->expiresInMinutes === 60 && str_contains($mail->render(), 'Set Your Password'));
        $this->assertFalse($user->is_active);
        $this->assertSame('pending_activation', $user->account_status);
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
        $this->post('/logout');
        $this->get(route('invitation.show', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Activate Account');
        $this->post('/invitation', $this->activation($user, $token))->assertRedirect('/login');
        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertSame('active', $user->account_status);
        $this->assertTrue(Hash::check('PrivateExample!2026', $user->password));
        $this->post('/invitation', $this->activation($user, $token))->assertSessionHasErrors('token');
        $this->post('/login', ['email' => $user->email, 'password' => 'PrivateExample!2026'])->assertRedirect('/');
    }

    public function test_expired_resend_cooldown_and_replacement_token(): void
    {
        $user = $this->invite();
        $old = $this->token();
        $this->post('/admin/users/'.$user->id.'/invitation')->assertSessionHasErrors('invitation');
        $this->travel(61)->minutes();
        $this->post('/logout');
        $this->post('/invitation', $this->activation($user, $old))->assertSessionHasErrors('token');
        $this->actingAs($this->user())->post('/admin/users/'.$user->id.'/invitation')->assertRedirect();
        $new = $this->token();
        $this->assertNotSame($new, $old);
        $this->assertFalse(Password::tokenExists($user, $old));
        $this->assertTrue(Password::tokenExists($user, $new));
    }

    public function test_pending_cannot_be_enabled_by_admin_and_disabled_token_cannot_activate(): void
    {
        $user = $this->invite();
        $token = $this->token();
        $this->put('/admin/users/'.$user->id, ['role' => 'employee', 'account_status' => 'active'])->assertSessionHasErrors('account_status');
        $this->put('/admin/users/'.$user->id, ['role' => 'employee', 'account_status' => 'disabled'])->assertRedirect('/admin/users');
        $this->post('/logout');
        $this->post('/invitation', $this->activation($user, $token))->assertSessionHasErrors('token');
        $this->post('/login', ['email' => $user->email, 'password' => 'PrivateExample!2026'])->assertSessionHasErrors('email');
    }

    public function test_pending_cannot_login_even_with_known_hash_or_forged_active_flag(): void
    {
        $user = $this->invite();
        $user->password = 'PrivateExample!2026';
        $user->is_active = true;
        $user->save();
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'PrivateExample!2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_duplicate_invitation_and_inactive_profile_rejected_and_no_password_in_admin_form(): void
    {
        $user = $this->invite();
        $id = $user->employee->id;
        $this->post('/admin/users', ['employee_id' => $id, 'role' => 'employee'])->assertSessionHasErrors('employee_id');
        $employee = Employee::whereNull('user_id')->first();
        $employee->is_active = false;
        $employee->save();
        $this->post('/admin/users', ['employee_id' => $employee->id, 'role' => 'hr'])->assertSessionHasErrors('employee_id');
        $this->get('/admin/users/create')->assertDontSee('name="password"', false)->assertSee('readonly', false);
        $this->get('/admin/users')->assertSee('Pending Activation');
    }

    public function test_correction_form_has_no_time_inputs_and_approval_uses_submit_not_review_time(): void
    {
        $employee = $this->user('employee');
        AttendanceCorrectionRequest::query()->delete();
        $this->actingAs($employee)->get('/employee/corrections/create')->assertDontSee('name="attendance_date"', false)->assertDontSee('name="time"', false)->assertDontSee('type="datetime-local"', false);
        $submitted = now()->copy();
        $this->post('/employee/corrections', ['type' => 'check_in', 'reason' => 'Koreksi perlu ditinjau HR.', 'photo' => UploadedFile::fake()->image('proof.jpg'), 'latitude' => -6.2, 'longitude' => 106.8, 'privacy_consent' => 1, 'captured_at' => now()->toIso8601String(), 'attendance_date' => '1999-01-01', 'time' => '01:00', 'requested_at' => '1999-01-01', 'submitted_at' => '1999-01-01'])->assertRedirect();
        $request = AttendanceCorrectionRequest::latest('id')->first();
        $this->assertTrue($request->submitted_at->equalTo($submitted));
        $this->travel(5)->hours();
        $this->actingAs($this->user('hr'))->post('/hr/corrections/'.$request->id.'/decision', ['decision' => 'approved'])->assertRedirect();
        $record = AttendanceRecord::whereDate('attendance_date', $submitted->toDateString())->firstOrFail();
        $this->assertTrue($record->check_in_at->equalTo($submitted));
        $this->assertSame('hr_approved_correction', $record->source);
        $this->assertTrue($request->approvalLogs()->first()->reviewed_at->equalTo(now()));
        $this->post('/hr/corrections/'.$request->id.'/decision', ['decision' => 'approved'])->assertForbidden();
        $this->assertSame(1, AttendanceRecord::whereDate('attendance_date', $submitted->toDateString())->count());
    }

    public function test_weak_password_and_other_roles_cannot_activate_or_resend(): void
    {
        $user = $this->invite();
        $token = $this->token();
        $this->actingAs($this->user('employee'))->post('/admin/users/'.$user->id.'/invitation')->assertForbidden();
        $this->post('/logout');
        $this->post('/invitation', array_replace($this->activation($user, $token), ['password' => 'weak', 'password_confirmation' => 'weak']))->assertSessionHasErrors('password');
        $this->assertSame('pending_activation', $user->fresh()->account_status);
    }

    public function test_mail_failure_leaves_pending_account_and_clear_retry_status(): void
    {
        Mail::swap(new class
        {
            public function to($email)
            {
                return $this;
            }

            public function send($mail)
            {
                throw new \RuntimeException('Simulated mail transport failure');
            }
        });
        $employee = Employee::whereNull('user_id')->first();
        $this->actingAs($this->user())->post('/admin/users', ['employee_id' => $employee->id, 'role' => 'employee'])->assertRedirect('/admin/users')->assertSessionHas('warning');
        $this->assertSame('pending_activation', $employee->fresh()->user->account_status);
        $this->assertFalse($employee->fresh()->user->is_active);
    }

    public function test_database_level_business_id_collision_is_retried(): void
    {
        $existing = Employee::first();
        $existing->business_id = '010120000517';
        $existing->save();
        $this->app->instance(EmployeeIdGenerator::class, new class extends EmployeeIdGenerator
        {
            private int $attempt = 0;

            public function generate(string $birthDate): string
            {
                return ++$this->attempt === 1 ? '010120000517' : '010220000517';
            }
        });
        $this->actingAs($this->user('hr'))->post('/hr/employees', $this->profileData())->assertRedirect('/hr/employees');
        $this->assertSame('010220000517', Employee::where('employee_number', 'NIK-NEW')->firstOrFail()->business_id);
    }

    public function test_hr_can_update_linked_email_without_changing_password_or_business_id(): void
    {
        $user = $this->user('employee');
        $profile = $user->employee;
        $id = $profile->business_id;
        $hash = $user->password;
        $this->actingAs($this->user('hr'))->put('/hr/employees/'.$profile->id, $this->profileData(['employee_number' => $profile->employee_number, 'email' => 'updated-login@facetrack.test', 'user_id' => $user->id]))->assertRedirect('/hr/employees');
        $this->assertSame('updated-login@facetrack.test', $user->fresh()->email);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame($id, $profile->fresh()->business_id);
    }

    public function test_mail_log_status_does_not_claim_inbox_delivery(): void
    {
        config(['mail.default' => 'log']);
        $employee = Employee::whereNull('user_id')->first();
        $this->actingAs($this->user())->post('/admin/users', ['employee_id' => $employee->id, 'role' => 'employee'])->assertSessionHas('success', fn ($message) => str_contains($message, 'tidak dikirim ke inbox'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\Support\EnforceRequestForgery;
use Tests\TestCase;

class UiQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        Mail::fake();
        config(['app.debug' => false]);
    }

    private function user(string $role): User
    {
        return User::where('email', $role.'@facetrack.test')->firstOrFail();
    }

    public function test_role_layouts_expose_skip_link_accessible_main_and_role_navigation_only(): void
    {
        foreach (['admin', 'hr', 'employee'] as $role) {
            $response = $this->actingAs($this->user($role))->get('/'.$role)->assertOk()->assertSee('href="#main-content"', false)->assertSee('id="main-content"', false)->assertSee('aria-controls="workspace-navigation"', false)->assertSee('aria-current="page"', false);
            if ($role !== 'admin') {
                $response->assertDontSee('href="'.route('admin.users.index').'"', false);
            }
            if ($role !== 'hr') {
                $response->assertDontSee('href="'.route('hr.corrections.index').'"', false);
            }
        }
    }

    public function test_tables_have_keyboard_scroll_regions_column_scopes_and_empty_states(): void
    {
        foreach (['admin' => ['/admin/users', '/admin/employees', '/admin/attendance'], 'hr' => ['/hr/employees', '/hr/attendance', '/hr/enrollment', '/hr/corrections', '/hr/leaves'], 'employee' => ['/employee/attendance', '/employee/corrections', '/employee/leaves']] as $role => $urls) {
            $this->actingAs($this->user($role));
            foreach ($urls as $url) {
                $this->get($url)->assertOk()->assertSee('role="region"', false)->assertSee('tabindex="0"', false)->assertSee('scope="col"', false);
            }
        }
        $this->actingAs($this->user('admin'))->get('/admin/employees?q=NothingMatches')->assertSee('Tidak ada karyawan yang cocok');
    }

    public function test_validation_summary_connects_errors_to_fields_and_preserves_safe_input(): void
    {
        $hr = $this->user('hr');
        $this->actingAs($hr)->from('/hr/employees/create')->post('/hr/employees', ['name' => 'Synthetic valid name', 'employee_number' => 'UI-TEST', 'email' => 'not-an-email', 'phone' => '00123'])->assertRedirect('/hr/employees/create');
        $this->get('/hr/employees/create')->assertOk()->assertViewHas('errors', fn ($errors) => $errors->any())->assertSee('data-validation-summary', false)->assertSee('data-error-field="email"', false)->assertSee('Synthetic valid name')->assertSee('00123');
    }

    public function test_untrusted_reason_is_escaped_in_hr_and_employee_detail_and_error_pages_hide_details(): void
    {
        $request = AttendanceCorrectionRequest::first();
        $request->reason = '<script>alert("private")</script>';
        $request->save();
        foreach (['hr' => '/hr/corrections/', 'employee' => '/employee/corrections/'] as $role => $path) {
            $this->actingAs($this->user($role))->get($path.$request->id)->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("private")</script>', false);
        }
        Route::middleware('web')->get('/__qa/error/{code}', fn ($code) => abort((int) $code, 'INTERNAL_SECRET_SHOULD_NOT_RENDER'));
        foreach ([403, 404, 405, 419, 429, 500, 503] as $code) {
            $this->get('/__qa/error/'.$code)->assertStatus($code)->assertSee('FaceTrack AI')->assertSee('Kembali ke beranda')->assertDontSee('INTERNAL_SECRET_SHOULD_NOT_RENDER');
        }
    }

    public function test_real_request_forgery_checks_block_mutations_without_tokens(): void
    {
        $this->app->bind(PreventRequestForgery::class, EnforceRequestForgery::class);
        $this->post('/login', ['email' => 'admin@facetrack.test', 'password' => 'DemoFaceTrack!2026'])->assertStatus(419)->assertSee('Sesi formulir berakhir');
        $this->actingAs($this->user('admin'))->post('/admin/users', ['employee_id' => 2, 'role' => 'employee'])->assertStatus(419);
        $this->actingAs($this->user('employee'))->post('/employee/corrections', [])->assertStatus(419);
        $this->post('/employee/leaves', [])->assertStatus(419);
        $this->actingAs($this->user('hr'))->post('/hr/corrections/'.AttendanceCorrectionRequest::first()->id.'/decision', ['decision' => 'approved'])->assertStatus(419);
        $this->post('/hr/leaves/'.LeaveRequest::first()->id.'/decision', ['decision' => 'approved'])->assertStatus(419);
        $this->assertDatabaseCount('approval_logs', 0);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('attendance_records', 6);
    }

    public function test_real_request_forgery_accepts_valid_session_token_and_json_failure_stays_json(): void
    {
        $this->app->bind(PreventRequestForgery::class, EnforceRequestForgery::class);
        $this->postJson('/login', ['email' => 'admin@facetrack.test', 'password' => 'DemoFaceTrack!2026'])->assertStatus(419)->assertJsonStructure(['message']);
        $this->withSession(['_token' => 'qa-session-token'])->post('/login', ['_token' => 'qa-session-token', 'email' => 'admin@facetrack.test', 'password' => 'DemoFaceTrack!2026'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->user('admin'));
    }

    public function test_inactive_session_is_revoked_and_forbidden_json_does_not_render_html(): void
    {
        $user = $this->user('employee');
        $this->actingAs($user)->getJson('/admin/users')->assertForbidden()->assertJsonStructure(['message']);
        $user->is_active = false;
        $user->save();
        $this->get('/employee')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_hr_decision_form_shows_validation_and_broken_image_recovery_hook(): void
    {
        $r = AttendanceCorrectionRequest::first();
        $r->is_demo = false;
        $r->save();
        $this->actingAs($this->user('hr'))->get('/hr/corrections/'.$r->id)->assertOk()->assertSee('data-decision-form', false)->assertSee('data-evidence-error', false)->assertSee('data-evidence-image', false);
        $this->post('/hr/corrections/'.$r->id.'/decision', ['decision' => 'rejected', 'notes' => ''])->assertSessionHasErrors('notes');
        $this->assertSame('pending', $r->fresh()->status);
        $this->assertDatabaseCount('approval_logs', 0);
    }
}

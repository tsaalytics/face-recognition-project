<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\KioskDevice;
use App\Models\User;
use App\Services\FaceExtractionClient;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\Support\EnforceRequestForgery;
use Tests\TestCase;

class StandaloneKioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-20 08:00:00', 'Asia/Jakarta'));
    }

    private function pairable(): array
    {
        $code = strtoupper(Str::random(16));
        $device = (new KioskDevice)->forceFill(['name' => 'Tablet Pintu Masuk', 'pairing_code_hash' => hash('sha256', $code),
            'pairing_expires_at' => now()->addMinutes(10)]);
        $device->save();
        return [$device, $code];
    }

    private function authorizedDevice(): KioskDevice
    {
        $token = Str::random(64);
        $device = (new KioskDevice)->forceFill(['name' => 'Tablet Terdaftar', 'activated_at' => now(),
            'expires_at' => now()->addDays(30), 'token_hash' => hash('sha256', $token)]);
        $device->save();
        $this->withCookie(config('kiosk.cookie'), $device->id.'.'.$token);
        $this->withCredentials();
        return $device;
    }

    public function test_public_kiosk_requires_device_authorization_even_with_hr_login(): void
    {
        $this->get('/kiosk')->assertRedirect(route('kiosk.activate'));
        $this->get('/kiosk/activate')->assertOk()->assertDontSee('workspace-sidebar');
        foreach (['/kiosk/start', '/kiosk/stop', '/kiosk/scan'] as $url) $this->postJson($url)->assertUnauthorized();
        $this->getJson('/kiosk/status')->assertUnauthorized();
        $this->actingAs(User::where('email', 'hr@facetrack.test')->firstOrFail());
        $this->get('/kiosk')->assertRedirect(route('kiosk.activate'));
        $this->postJson('/kiosk/scan')->assertUnauthorized();
    }

    public function test_hr_can_issue_codes_but_other_roles_and_device_only_visitors_cannot_manage_devices(): void
    {
        $this->get('/hr/kiosk/devices')->assertRedirect('/login');
        foreach (['admin', 'employee'] as $role) {
            $this->actingAs(User::where('email', $role.'@facetrack.test')->firstOrFail());
            $this->get('/hr/kiosk/devices')->assertForbidden();
            $this->post('/hr/kiosk/devices', ['name' => 'Forbidden'])->assertForbidden();
        }
        $this->actingAs(User::where('email', 'hr@facetrack.test')->firstOrFail());
        $this->post('/hr/kiosk/devices', ['name' => 'Tablet Lobby'])->assertRedirect(route('hr.kiosk-devices.index'))->assertSessionHas('kiosk_pairing_code');
        $device = KioskDevice::sole();
        $code = str_replace('-', '', decrypt(session('kiosk_pairing_code')));
        $this->assertSame(hash('sha256', $code), $device->pairing_code_hash);
        $this->assertNull($device->token_hash);
        $this->get('/hr/kiosk/devices')->assertOk()->assertSee('Tablet Lobby')->assertSee('Menunggu aktivasi');
    }

    public function test_activation_is_single_use_scoped_cookie_and_logs_out_dashboard_user(): void
    {
        [$device, $code] = $this->pairable();
        $this->actingAs(User::where('email', 'hr@facetrack.test')->firstOrFail());
        $response = $this->post('/kiosk/activate', ['activation_code' => $code])->assertRedirect(route('kiosk.home'));
        $cookie = $response->getCookie(config('kiosk.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('/kiosk', $cookie->getPath());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertGuest();
        $this->assertTrue($device->fresh()->authorized());
        $this->assertNull($device->fresh()->pairing_code_hash);
        $this->postJson('/kiosk/activate', ['activation_code' => $code])->assertUnprocessable();
        $this->withCookie(config('kiosk.cookie'), $cookie->getValue())->withCredentials();
        $this->get('/kiosk')->assertOk()->assertSee('data-face-kiosk', false)->assertDontSee('workspace-sidebar');
        $this->assertGuest();
    }

    public function test_expired_or_revoked_pairing_codes_are_rejected_without_flashing_the_secret(): void
    {
        [$device, $code] = $this->pairable();
        $device->forceFill(['pairing_expires_at' => now()->subSecond()])->save();
        $this->from('/kiosk/activate')->post('/kiosk/activate', ['activation_code' => $code])
            ->assertRedirect('/kiosk/activate')->assertSessionHasErrors('activation_code')->assertSessionMissing('_old_input.activation_code');
        $this->assertNull($device->fresh()->activated_at);
        [$revoked, $code] = $this->pairable();
        $revoked->forceFill(['revoked_at' => now()])->save();
        $this->postJson('/kiosk/activate', ['activation_code' => $code])->assertUnprocessable();
    }

    public function test_device_only_session_sees_standalone_camera_but_no_dashboard_access(): void
    {
        $device = $this->authorizedDevice();
        $this->get('/kiosk')->assertOk()->assertViewHas('device', fn ($value) => $value->id === $device->id)
            ->assertSee('data-face-kiosk', false)->assertSee('action="'.route('kiosk.scan').'"', false)
            ->assertSee('data-result-dialog', false)->assertDontSee('data-consent', false)
            ->assertDontSee('Absensi otomatis berdasarkan jadwal server WIB')
            ->assertDontSee('workspace-sidebar')->assertDontSee('workspace-navigation')->assertDontSee('href="'.route('hr.home').'"', false);
        $this->assertGuest();
        foreach (['/hr', '/admin', '/employee', '/hr/employees', '/hr/kiosk/devices'] as $url) $this->get($url)->assertRedirect('/login');
        $this->getJson('/kiosk/status')->assertOk()->assertJsonPath('mode', 'check_in');
        $this->postJson('/kiosk/stop')->assertOk();
        $this->get('/kiosk')->assertOk()->assertViewHas('paused', true);
        $this->postJson('/kiosk/start')->assertOk()->assertJsonPath('paused', false);
        $device->forceFill(['revoked_at' => now()])->save();
        $this->getJson('/kiosk/status')->assertUnauthorized();
        $this->postJson('/kiosk/start')->assertUnauthorized();
    }

    private function recognitionFixture(): array
    {
        $employee = Employee::firstOrFail();
        $employee->forceFill(['enrollment_status' => 'enrolled'])->save();
        (new EmployeeFaceEmbedding)->forceFill(['employee_id' => $employee->id, 'face_embedding' => [1, ...array_fill(0, 511, 0)],
            'embedding_dimension' => 512, 'model_name' => 'buffalo_m', 'model_identifier' => 'buffalo_m:sha256:'.str_repeat('a', 64),
            'enrollment_status' => 'enrolled', 'quality' => [], 'enrolled_at' => now()])->save();
        config(['face.similarity_threshold' => .8]);
        $probe = ['model_identifier' => 'buffalo_m:sha256:'.str_repeat('a', 64), 'embedding' => [1, ...array_fill(0, 511, 0)]];
        $image = imagecreatetruecolor(100, 100);
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        return [$employee, $probe, ['consent' => true, 'samples' => ['data:image/png;base64,'.base64_encode($bytes)]]];
    }

    public function test_device_can_record_attendance_without_impersonating_hr(): void
    {
        $this->authorizedDevice();
        [$employee, $probe, $data] = $this->recognitionFixture();
        unset($data['consent']);
        $this->mock(FaceExtractionClient::class)->shouldReceive('extract')->andReturn($probe);
        $this->get('/kiosk')->assertOk();
        $this->postJson('/kiosk/scan', $data + ['employee_id' => 999, 'type' => 'check_out'])->assertOk()->assertJsonPath('type', 'check_in')
            ->assertJsonPath('employee_name', $employee->name)->assertJsonPath('status', 'present');
        $this->assertDatabaseHas('attendance_records', ['employee_id' => $employee->id, 'source' => 'face_recognition', 'status' => 'present']);
        $this->postJson('/kiosk/scan', $data)->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_device_revoked_during_inference_cannot_commit_attendance(): void
    {
        $device = $this->authorizedDevice();
        [, $probe, $data] = $this->recognitionFixture();
        $this->mock(FaceExtractionClient::class)->shouldReceive('extract')->andReturnUsing(function () use ($device, $probe) {
            $device->forceFill(['revoked_at' => now()])->save();
            return $probe;
        });
        $this->get('/kiosk')->assertOk();
        $this->postJson('/kiosk/scan', $data)->assertForbidden();
        $this->assertDatabaseMissing('attendance_records', ['source' => 'face_recognition']);
    }

    public function test_device_expiry_and_tampered_credentials_fail_closed(): void
    {
        $device = $this->authorizedDevice();
        $device->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->get('/kiosk')->assertRedirect(route('kiosk.activate'));
        $this->postJson('/kiosk/scan')->assertUnauthorized();
        $device->forceFill(['expires_at' => now()->addDay()])->save();
        $this->withCookie(config('kiosk.cookie'), $device->id.'.'.Str::random(64));
        $this->getJson('/kiosk/status')->assertUnauthorized();
    }

    public function test_device_endpoints_require_csrf_and_hr_can_revoke_access(): void
    {
        $device = $this->authorizedDevice();
        $this->app->bind(PreventRequestForgery::class, EnforceRequestForgery::class);
        $this->postJson('/kiosk/start')->assertStatus(419);
        $this->app->bind(PreventRequestForgery::class, PreventRequestForgery::class);
        $this->actingAs(User::where('email', 'hr@facetrack.test')->firstOrFail());
        $this->post(route('hr.kiosk-devices.revoke', $device))->assertRedirect(route('hr.kiosk-devices.index'));
        $this->assertFalse($device->fresh()->authorized());
        $this->assertNull($device->fresh()->token_hash);
        Auth::logout();
        $this->postJson('/kiosk/scan')->assertUnauthorized();
    }

    public function test_network_activation_requires_https_and_sets_a_secure_cookie(): void
    {
        [$device, $code] = $this->pairable();
        $this->post('http://tablet.example/kiosk/activate', ['activation_code' => $code])->assertForbidden();
        $this->assertNull($device->fresh()->activated_at);
        $response = $this->post('https://localhost/kiosk/activate', ['activation_code' => $code])->assertStatus(302);
        $this->assertTrue($response->getCookie(config('kiosk.cookie'))->isSecure());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeFaceEmbedding;
use App\Models\FaceEnrollmentAudit;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EnforceRequestForgery;
use Tests\TestCase;

class FaceEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        $this->seed();
        $this->employee = Employee::firstOrFail();
        config(['face.api_key' => str_repeat('x', 40), 'face.service_url' => 'http://ai.test']);
        Http::preventStrayRequests();
        $this->fakeAi(['ai.test/*' => Http::response($this->aiResult())]);
    }

    private function fakeAi($callback): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($callback);
    }

    private function actor(string $role = 'hr'): User
    {
        return User::where('email', $role.'@facetrack.test')->firstOrFail();
    }

    private function url(): string
    {
        return '/hr/employees/'.$this->employee->id.'/face';
    }

    private function aiResult(float $angle = 0): array
    {
        return ['status' => 'ok', 'model_name' => 'buffalo_m', 'model_identifier' => 'buffalo_m:sha256:'.str_repeat('a', 64),
            'embedding_dimension' => 512, 'embedding' => [cos($angle), sin($angle), ...array_fill(0, 510, 0)],
            'quality' => ['detection_score' => .99, 'face_width' => 120, 'face_height' => 120, 'blur_variance' => 100, 'brightness' => 120]];
    }

    private function data(int $version = 0): array
    {
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return ['consent' => true, 'enrollment_version' => $version, 'samples' => array_fill(0, 3, 'data:image/png;base64,'.base64_encode($bytes))];
    }

    public function test_hr_enrollment_is_encrypted_deduplicated_and_never_returned_or_stored_as_photo(): void
    {
        $before = Storage::disk('local')->allFiles();
        $response = $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertOk()->assertJsonPath('version', 1);
        $record = EmployeeFaceEmbedding::firstOrFail();
        $this->assertSame(512, count($record->face_embedding));
        $this->assertSame($this->employee->id, $record->employee->id);
        $raw = DB::table('employee_face_embeddings')->value('face_embedding');
        $this->assertNotSame(json_encode($record->face_embedding), $raw);
        $this->assertArrayNotHasKey('face_embedding', $record->toArray());
        $this->assertSame('enrolled', $this->employee->fresh()->enrollment_status);
        $this->assertDatabaseCount('employee_face_embeddings', 1);
        $this->assertDatabaseHas('face_enrollment_audits', ['action' => 'enroll', 'sample_count' => 3, 'stored_count' => 1, 'consent_version' => 'biometric-demo-v1']);
        $this->assertSame($before, Storage::disk('local')->allFiles());
        $response->assertDontSee('embedding')->assertDontSee('base64');
        $this->get($this->url())->assertOk()->assertDontSee($raw)->assertDontSee('X-API-Key')->assertDontSee('face_embedding');
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->hasHeader('X-API-Key', str_repeat('x', 40)) && str_starts_with($request->body(), "\x89PNG"));
        $this->assertDatabaseCount('attendance_records', 6);
    }

    public function test_guest_admin_employee_and_disabled_hr_cannot_access_or_mutate(): void
    {
        $this->get($this->url())->assertRedirect('/login');
        foreach (['admin', 'employee'] as $role) {
            $this->actingAs($this->actor($role))->get($this->url())->assertForbidden();
            $this->postJson($this->url(), $this->data())->assertForbidden();
            $this->deleteJson($this->url(), ['confirmation' => true, 'enrollment_version' => 0])->assertForbidden();
        }
        $hr = $this->actor();
        $hr->forceFill(['is_active' => false])->save();
        $this->actingAs($hr)->postJson($this->url(), $this->data())->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_consistency_and_model_mismatch_fail_without_changing_old_enrollment(): void
    {
        $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertOk();
        $old = DB::table('employee_face_embeddings')->value('face_embedding');
        $this->fakeAi(['ai.test/*' => Http::sequence()->push($this->aiResult())->push($this->aiResult(2))]);
        $this->postJson($this->url(), $this->data(1))->assertUnprocessable()->assertJsonValidationErrors('samples');
        $this->assertSame($old, DB::table('employee_face_embeddings')->value('face_embedding'));
        $this->assertDatabaseCount('face_enrollment_audits', 1);
        $changed = $this->aiResult();
        $changed['model_identifier'] = 'buffalo_m:sha256:'.str_repeat('b', 64);
        $this->fakeAi(['ai.test/*' => Http::sequence()->push($this->aiResult())->push($changed)]);
        $this->postJson($this->url(), $this->data(1))->assertUnprocessable();
        $this->assertSame(1, $this->employee->fresh()->face_enrollment_version);
    }

    public function test_reenroll_atomically_replaces_and_delete_removes_rows_with_audit(): void
    {
        $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertOk();
        $oldId = EmployeeFaceEmbedding::first()->id;
        $this->fakeAi(['ai.test/*' => Http::sequence()->push($this->aiResult())->push($this->aiResult(.2))->push($this->aiResult(.3))]);
        $this->postJson($this->url(), $this->data(1))->assertOk();
        $this->assertDatabaseMissing('employee_face_embeddings', ['id' => $oldId]);
        $this->assertDatabaseCount('employee_face_embeddings', 3);
        $this->assertDatabaseHas('face_enrollment_audits', ['action' => 're_enroll', 'version' => 2]);
        $this->delete($this->url(), ['confirmation' => true, 'enrollment_version' => 2])->assertRedirect($this->url());
        $this->assertDatabaseCount('employee_face_embeddings', 0);
        $this->assertDatabaseHas('face_enrollment_audits', ['action' => 'delete', 'version' => 3]);
        $this->assertSame('not_enrolled', $this->employee->fresh()->enrollment_status);
    }

    public function test_stale_versions_do_not_overwrite_or_delete_newer_enrollment(): void
    {
        $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertOk();
        $this->postJson($this->url(), $this->data())->assertConflict();
        $this->deleteJson($this->url(), ['confirmation' => true, 'enrollment_version' => 0])->assertConflict();
        $this->assertDatabaseCount('face_enrollment_audits', 1);
        $this->assertDatabaseCount('employee_face_embeddings', 1);
    }

    public function test_consent_count_format_and_profile_validation_precede_inference(): void
    {
        $this->actingAs($this->actor());
        foreach ([['consent' => false], ['samples' => []], ['samples' => array_fill(0, 6, 'bad')], ['samples' => array_fill(0, 3, 'bad')]] as $invalid) {
            $this->postJson($this->url(), array_replace($this->data(), $invalid))->assertUnprocessable();
        }
        $this->employee->forceFill(['is_active' => false])->save();
        $this->postJson($this->url(), $this->data())->assertUnprocessable();
        $this->employee->forceFill(['is_active' => true, 'business_id' => null])->save();
        $this->postJson($this->url(), $this->data())->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_service_timeout_and_invalid_output_do_not_persist_anything(): void
    {
        $this->actingAs($this->actor());
        $this->fakeAi(fn () => throw new ConnectionException('SECRET_API_DETAILS'));
        $this->postJson($this->url(), $this->data())->assertUnprocessable()->assertDontSee('SECRET_API_DETAILS');
        $bad = $this->aiResult();
        $bad['embedding'] = array_fill(0, 512, 0);
        $this->fakeAi(['ai.test/*' => Http::response($bad)]);
        $this->postJson($this->url(), $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('employee_face_embeddings', 0);
        $this->assertDatabaseCount('face_enrollment_audits', 0);
    }

    public function test_failed_audit_rolls_back_replacement_and_status(): void
    {
        $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertOk();
        $old = DB::table('employee_face_embeddings')->value('face_embedding');
        FaceEnrollmentAudit::creating(fn () => throw new \RuntimeException('INTERNAL_SECRET'));
        $this->postJson($this->url(), $this->data(1))->assertStatus(503)->assertDontSee('INTERNAL_SECRET');
        $this->assertSame($old, DB::table('employee_face_embeddings')->value('face_embedding'));
        $this->assertSame(1, $this->employee->fresh()->face_enrollment_version);
        $this->assertDatabaseCount('face_enrollment_audits', 1);
    }

    public function test_images_are_not_flashed_to_session_on_html_validation_failure(): void
    {
        $data = $this->data();
        $data['consent'] = false;
        $this->actingAs($this->actor())->from($this->url())->post($this->url(), $data)->assertRedirect($this->url());
        $this->assertNull(session()->getOldInput('samples'));
    }

    public function test_delete_requires_confirmation_and_audit_is_immutable(): void
    {
        $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertOk();
        $this->deleteJson($this->url(), ['enrollment_version' => 1])->assertUnprocessable();
        $this->expectException(\LogicException::class);
        FaceEnrollmentAudit::first()->delete();
    }

    public function test_version_and_actor_are_checked_again_after_slow_inference(): void
    {
        $this->fakeAi(function () {
            Employee::whereKey($this->employee->id)->update(['face_enrollment_version' => 1]);

            return Http::response($this->aiResult());
        });
        $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertConflict();
        $this->assertDatabaseCount('employee_face_embeddings', 0);
        $this->employee->refresh()->forceFill(['face_enrollment_version' => 0])->save();
        $hr = $this->actor();
        $this->fakeAi(function () use ($hr) {
            User::whereKey($hr->id)->update(['is_active' => false]);

            return Http::response($this->aiResult());
        });
        $this->postJson($this->url(), $this->data())->assertForbidden();
        $this->assertDatabaseCount('face_enrollment_audits', 0);
    }

    public function test_face_routes_enforce_csrf_and_enrollment_rate_limit(): void
    {
        $this->app->bind(PreventRequestForgery::class, EnforceRequestForgery::class);
        $this->actingAs($this->actor())->postJson($this->url(), $this->data())->assertStatus(419);
        $this->deleteJson($this->url(), ['confirmation' => true, 'enrollment_version' => 0])->assertStatus(419);
        $this->withMiddleware(ThrottleRequests::class);
        $this->withSession(['_token' => 'csrf-test']);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson($this->url(), [], ['X-CSRF-TOKEN' => 'csrf-test'])->assertUnprocessable();
        }
        $this->postJson($this->url(), [], ['X-CSRF-TOKEN' => 'csrf-test'])->assertStatus(429);
        Http::assertNothingSent();
    }
}

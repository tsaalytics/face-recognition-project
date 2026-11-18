<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactEmployeeDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $this->actingAs(User::where('email', 'hr@facetrack.test')->firstOrFail());
    }

    public function test_directory_has_real_actions_and_defaults_to_ten_rows(): void
    {
        $this->get('/hr/employees')->assertOk()
            ->assertViewHas('employees', fn ($rows) => $rows->perPage() === 10)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('data-list-filter', false)
            ->assertSee('href="'.route('hr.employees.create').'"', false)
            ->assertDontSee('href="'.route('hr.enrollment').'"', false)
            ->assertDontSee('Export')->assertDontSee('Download CSV');
    }

    public function test_server_pagination_preserves_filters_including_inactive_status(): void
    {
        $division = Employee::firstOrFail()->division_id;
        for ($i = 1; $i <= 26; $i++) {
            Employee::create(['division_id' => $division, 'employee_number' => 'COMPACT-'.$i,
                'name' => 'Compact Employee '.sprintf('%02d', $i), 'email' => 'compact'.$i.'@example.test',
                'position' => 'Operator', 'is_active' => false]);
        }
        $filters = ['q' => 'Compact Employee', 'division_id' => $division, 'active' => '0'];
        foreach ([5, 10, 25, 50] as $perPage) {
            $this->get('/hr/employees?'.http_build_query($filters + ['per_page' => $perPage]))->assertOk()
                ->assertViewHas('employees', fn ($rows) => $rows->total() === 26 && $rows->perPage() === $perPage && $rows->count() === min(26, $perPage))
                ->assertSee('3 filter aktif');
        }
        $this->get('/hr/employees?'.http_build_query($filters + ['per_page' => 5, 'page' => 2]))->assertOk()
            ->assertViewHas('employees', function ($rows) use ($division) {
                parse_str(parse_url($rows->nextPageUrl(), PHP_URL_QUERY), $next);
                return $rows->currentPage() === 2 && $rows->first()->name === 'Compact Employee 06'
                    && $next === ['q' => 'Compact Employee', 'division_id' => (string) $division, 'active' => '0', 'per_page' => '5', 'page' => '3'];
            });
    }

    public function test_invalid_row_limits_are_rejected_and_related_lists_default_to_ten_rows(): void
    {
        foreach (['0', '-5', '15', '1000', 'all', ['10']] as $limit) {
            $this->getJson('/hr/employees?'.http_build_query(['per_page' => $limit]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
        }
        $this->get('/hr/enrollment')->assertRedirect(route('hr.employees.index'));
        $this->actingAs(User::where('email', 'admin@facetrack.test')->firstOrFail());
        $this->get('/hr/employees')->assertForbidden();
        $this->get('/admin/employees')->assertForbidden();
    }
}

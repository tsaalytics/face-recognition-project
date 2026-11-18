<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactListsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    private function lists(): array
    {
        return [
            'admin' => ['users' => 'users', 'employees' => 'employees', 'attendance' => 'records'],
            'hr' => ['corrections' => 'requests', 'leaves' => 'requests'],
            'employee' => ['attendance' => 'records', 'corrections' => 'requests', 'leaves' => 'requests'],
        ];
    }

    public function test_compact_lists_support_allowed_page_sizes_and_reject_invalid_limits(): void
    {
        foreach ($this->lists() as $role => $pages) {
            $this->actingAs(User::where('email', $role.'@facetrack.test')->firstOrFail());
            foreach ($pages as $page => $variable) {
                $url = '/'.$role.'/'.$page;
                $this->get($url)->assertOk()->assertViewHas($variable, fn ($rows) => $rows->perPage() === 10)
                    ->assertSee('aria-label="Breadcrumb"', false)->assertSee('data-list-filter', false)
                    ->assertSee('data-per-page-form', false)->assertSee('class="compact-list"', false)
                    ->assertSee('class="ft-pagination"', false)->assertSee('Sebelumnya')->assertSee('Selanjutnya')->assertDontSee('Menampilkan')
                    ->assertDontSee('Download CSV')->assertDontSee('Export');
                foreach ([5, 10, 25, 50] as $size) {
                    $this->get($url.'?per_page='.$size)->assertOk()
                        ->assertViewHas($variable, fn ($rows) => $rows->perPage() === $size && $rows->count() <= $size);
                }
                $this->getJson($url.'?per_page=999')->assertUnprocessable()->assertJsonValidationErrors('per_page');
            }
        }
    }

    public function test_employee_filters_and_pagination_never_include_other_employees_records(): void
    {
        $user = User::where('email', 'employee@facetrack.test')->firstOrFail();
        $employee = $user->employee;
        $this->actingAs($user);
        $this->get('/employee/attendance?per_page=5&from='.today()->subDays(7)->toDateString().'&to='.today()->toDateString())->assertOk()
            ->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->every(fn ($row) => $row->employee_id === $employee->id));
        foreach (['corrections', 'leaves'] as $kind) {
            $this->get('/employee/'.$kind.'?status=pending&per_page=5')->assertOk()
                ->assertViewHas('requests', fn ($rows) => $rows->every(fn ($row) => $row->employee_id === $employee->id && $row->status === 'pending'))
                ->assertSee('1 filter aktif')->assertSee('href="'.route('employee.'.$kind.'.create').'"', false);
            $this->get('/employee/'.$kind.'?status=rejected&per_page=5')->assertOk()
                ->assertViewHas('requests', fn ($rows) => $rows->total() === 0);
        }
        $employee->update(['is_active' => false]);
        foreach (['corrections', 'leaves'] as $kind) {
            $this->get('/employee/'.$kind)->assertOk()->assertDontSee('href="'.route('employee.'.$kind.'.create').'"', false)
                ->assertSee('Pengajuan belum tersedia');
        }
    }

    public function test_enrollment_and_admin_directory_preserve_inactive_filter(): void
    {
        $employee = Employee::firstOrFail();
        $employee->update(['is_active' => false]);
        foreach (['hr' => '/hr/employees', 'admin' => '/admin/employees'] as $role => $url) {
            $this->actingAs(User::where('email', $role.'@facetrack.test')->firstOrFail());
            $this->get($url.'?active=0&per_page=5')->assertOk()
                ->assertViewHas('employees', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $employee->id)
                ->assertSee('1 filter aktif');
        }
        $this->get('/admin/employees')->assertDontSee('href="'.route('hr.employees.create').'"', false);
    }
}

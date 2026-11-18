<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDirectoryPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
    }

    public function test_directory_shows_only_summary_columns_and_authorized_actions(): void
    {
        $employee = Employee::firstOrFail();
        foreach (['hr', 'admin'] as $role) {
            $this->actingAs(User::where('email', $role.'@facetrack.test')->firstOrFail());
            $response = $this->get('/'.$role.'/employees')->assertOk()
                ->assertSeeInOrder(['ID Karyawan</th>', 'Nama</th>', 'Divisi</th>', 'Email</th>', 'Status</th>', 'Aksi</th>'], false)
                ->assertSee($employee->business_id)->assertSee($employee->name)->assertSee($employee->email)
                ->assertDontSee($employee->employee_number)->assertDontSee($employee->phone)
                ->assertDontSee($employee->home_address)->assertDontSee('Kontak</th>', false)
                ->assertSee('href="'.route($role.'.employees.show', $employee).'"', false)
                ->assertSee('Lihat detail '.$employee->name);
            if ($role === 'hr') {
                $response->assertSee('href="'.route('hr.employees.edit', $employee).'"', false);
            } else {
                $response->assertDontSee('href="'.route('hr.employees.edit', $employee).'"', false);
            }
        }
    }

    public function test_detail_links_resolve_to_authorized_private_read_pages(): void
    {
        $employee = Employee::firstOrFail();
        foreach (['hr', 'admin'] as $role) {
            $this->actingAs(User::where('email', $role.'@facetrack.test')->firstOrFail());
            $response = $this->get(route($role.'.employees.show', $employee))->assertOk()
                ->assertSee($employee->employee_number)->assertSee($employee->business_id)
                ->assertSee($employee->phone)->assertSee($employee->home_address)
                ->assertSee($employee->date_of_birth->format('d M Y'))
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertDontSee('face_embedding')->assertDontSee('model_identifier');
            if ($role === 'admin') {
                $response->assertDontSee('href="'.route('hr.employees.edit', $employee).'"', false);
                $this->get(route('hr.employees.edit', $employee))->assertForbidden();
                $this->put(route('hr.employees.update', $employee), ['name' => 'Unauthorized change'])->assertForbidden();
                $this->assertSame($employee->name, $employee->fresh()->name);
            }
        }
    }

    public function test_employee_and_guest_cannot_use_directory_detail_routes(): void
    {
        $employee = Employee::whereNull('user_id')->firstOrFail();
        foreach (['hr', 'admin'] as $role) {
            $this->get(route($role.'.employees.show', $employee))->assertRedirect('/login');
        }
        $this->actingAs(User::where('email', 'employee@facetrack.test')->firstOrFail());
        foreach (['hr', 'admin'] as $role) {
            $this->get('/'.$role.'/employees')->assertForbidden();
            $this->get(route($role.'.employees.show', $employee))->assertForbidden();
        }
    }
}

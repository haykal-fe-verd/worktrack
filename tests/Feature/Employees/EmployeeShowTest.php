<?php

namespace Tests\Feature\Employees;

use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function viewer(): User
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('viewer');

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $version = file_exists($manifest = public_path('build/manifest.json'))
            ? hash_file('xxh128', $manifest)
            : '';

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
        ];
    }

    public function test_page_renders_with_assignment_history(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['nama' => 'Siti']);
        $period = JobPeriod::factory()->create(['no_dokumen' => '12345']);
        Assignment::factory()->create([
            'employee_id' => $employee->id,
            'job_period_id' => $period->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get("/employees/{$employee->id}", $this->inertiaHeaders());

        $response->assertOk();
        $response->assertJsonPath('component', 'Employees/Show');
        $response->assertJsonPath('props.employee.nama', 'Siti');
        $response->assertJsonPath('props.assignments.0.no_dokumen', '12345');
    }

    public function test_viewer_sees_masked_nik(): void
    {
        $viewer = $this->viewer();
        $employee = Employee::factory()->create(['nik' => '1234567890123456']);

        $response = $this->actingAs($viewer)->get("/employees/{$employee->id}", $this->inertiaHeaders());

        $response->assertOk();
        $this->assertNotSame('1234567890123456', $response->json('props.employee.nik'));
    }
}

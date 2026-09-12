<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentCreateTest extends TestCase
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

    public function test_viewer_cannot_access_assign_form_or_submit(): void
    {
        $viewer = $this->viewer();
        $period = JobPeriod::factory()->create();

        $this->actingAs($viewer)->get("/job-periods/{$period->id}/assignments/create")->assertForbidden();
        $this->actingAs($viewer)->post("/job-periods/{$period->id}/assignments", [])->assertForbidden();
    }

    public function test_admin_can_assign_an_active_employee_to_an_active_period(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
            'tarif_jual' => 500000,
            'tarif_bayar' => 400000,
        ]);

        $response->assertRedirect(route('job-periods.show', $period));

        $assignment = Assignment::where('employee_id', $employee->id)->firstOrFail();
        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);
        $this->assertTrue($assignment->is_current);
        $this->assertSame($period->id, $assignment->job_period_id);
    }

    public function test_cannot_assign_to_an_inactive_job_period(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->berakhir()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
        ])->assertForbidden();
    }

    public function test_cannot_assign_an_inactive_employee(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();
        $employee = Employee::factory()->nonAktif()->create();

        $response = $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }

    public function test_cannot_assign_the_same_employee_twice_while_current(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();
        $employee = Employee::factory()->create();
        Assignment::factory()->create([
            'employee_id' => $employee->id,
            'job_period_id' => $period->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post("/job-periods/{$period->id}/assignments", [
            'employee_id' => $employee->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response->assertSessionHasErrors('employee_id');
    }
}

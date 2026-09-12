<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_factory_creates_a_valid_row_with_correct_casts(): void
    {
        $assignment = Assignment::factory()->create();

        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);
        $this->assertTrue($assignment->is_current);
        $this->assertIsString($assignment->tarif_jual);
        $this->assertInstanceOf(Carbon::class, $assignment->tanggal_mulai);
    }

    public function test_employee_has_many_assignments(): void
    {
        $employee = Employee::factory()->create();
        Assignment::factory()->count(2)->create(['employee_id' => $employee->id]);

        $this->assertCount(2, $employee->assignments);
    }

    public function test_job_period_has_many_assignments(): void
    {
        $jobPeriod = JobPeriod::factory()->create();
        Assignment::factory()->count(3)->create(['job_period_id' => $jobPeriod->id]);

        $this->assertCount(3, $jobPeriod->assignments);
    }

    public function test_previous_assignment_relation_resolves(): void
    {
        $old = Assignment::factory()->diperbarui()->create();
        $new = Assignment::factory()->create(['previous_assignment_id' => $old->id]);

        $this->assertTrue($new->previousAssignment->is($old));
    }
}

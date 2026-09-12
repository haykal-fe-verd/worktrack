<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentEndTest extends TestCase
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

    public function test_viewer_cannot_access_end_form_or_submit(): void
    {
        $viewer = $this->viewer();
        $assignment = Assignment::factory()->create();

        $this->actingAs($viewer)->get("/assignments/{$assignment->id}/end")->assertForbidden();
        $this->actingAs($viewer)->patch("/assignments/{$assignment->id}/end", [])->assertForbidden();
    }

    public function test_ending_sets_status_selesai_and_keeps_is_current_true_with_no_new_row(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response = $this->actingAs($admin)->patch("/assignments/{$assignment->id}/end", [
            'tanggal_selesai' => '2025-09-15',
        ]);

        $response->assertRedirect(route('job-periods.show', $assignment->job_period_id));

        $assignment->refresh();
        $this->assertSame(AssignmentStatus::Selesai, $assignment->status);
        $this->assertTrue($assignment->is_current);
        $this->assertSame('2025-09-15', $assignment->tanggal_selesai->format('Y-m-d'));
        $this->assertSame(0, Assignment::where('previous_assignment_id', $assignment->id)->count());
    }

    public function test_tanggal_selesai_must_be_on_or_after_tanggal_mulai(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-10',
        ]);

        $response = $this->actingAs($admin)->patch("/assignments/{$assignment->id}/end", [
            'tanggal_selesai' => '2025-09-01',
        ]);

        $response->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_cannot_end_an_already_ended_assignment(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->selesai()->create();

        $this->actingAs($admin)->get("/assignments/{$assignment->id}/end")->assertNotFound();
    }
}

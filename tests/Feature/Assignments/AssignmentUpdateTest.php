<?php

namespace Tests\Feature\Assignments;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentUpdateTest extends TestCase
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

    public function test_viewer_cannot_access_edit_form_or_submit(): void
    {
        $viewer = $this->viewer();
        $assignment = Assignment::factory()->create();

        $this->actingAs($viewer)->get("/assignments/{$assignment->id}/edit")->assertForbidden();
        $this->actingAs($viewer)->put("/assignments/{$assignment->id}", [])->assertForbidden();
    }

    public function test_changing_dates_creates_a_new_version_and_closes_the_old_one(): void
    {
        $admin = $this->admin();
        $old = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $response = $this->actingAs($admin)->put("/assignments/{$old->id}", [
            'tanggal_mulai' => '2025-09-10',
            'tanggal_selesai' => null,
            'tarif_jual' => $old->tarif_jual,
            'tarif_bayar' => $old->tarif_bayar,
        ]);

        $response->assertSessionHas('success', 'Penugasan berhasil diperbarui.');
        $response->assertRedirect(route('job-periods.show', $old->job_period_id));

        $old->refresh();
        $this->assertSame(AssignmentStatus::Diperbarui, $old->status);
        $this->assertFalse($old->is_current);

        $new = Assignment::where('previous_assignment_id', $old->id)->firstOrFail();
        $this->assertTrue($new->is_current);
        $this->assertSame(AssignmentStatus::Aktif, $new->status);
        $this->assertSame('2025-09-10', $new->tanggal_mulai->format('Y-m-d'));
    }

    public function test_changing_only_tarif_updates_in_place_without_a_new_version(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => null,
        ]);

        $response = $this->actingAs($admin)->put("/assignments/{$assignment->id}", [
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => null,
            'tarif_jual' => 999999,
            'tarif_bayar' => 888888,
        ]);

        $response->assertSessionHas('success', 'Penugasan berhasil diperbarui.');

        $assignment->refresh();
        $this->assertSame('999999.00', $assignment->tarif_jual);
        $this->assertTrue($assignment->is_current);
        $this->assertSame(AssignmentStatus::Aktif, $assignment->status);
        $this->assertSame(0, Assignment::where('previous_assignment_id', $assignment->id)->count());
    }

    public function test_cannot_edit_a_non_current_assignment(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->diperbarui()->create();

        $this->actingAs($admin)->get("/assignments/{$assignment->id}/edit")->assertNotFound();
    }

    public function test_cannot_edit_or_update_an_ended_assignment(): void
    {
        $admin = $this->admin();
        $assignment = Assignment::factory()->selesai()->create([
            'created_by' => $admin->id,
            'tanggal_mulai' => '2025-09-01',
        ]);

        $this->assertTrue($assignment->is_current);

        $this->actingAs($admin)->get("/assignments/{$assignment->id}/edit")->assertNotFound();

        $this->actingAs($admin)->put("/assignments/{$assignment->id}", [
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => null,
            'tarif_jual' => $assignment->tarif_jual,
            'tarif_bayar' => $assignment->tarif_bayar,
        ])->assertNotFound();
    }
}

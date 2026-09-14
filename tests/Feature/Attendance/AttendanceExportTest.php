<?php

namespace Tests\Feature\Attendance;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceExportTest extends TestCase
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

    public function test_viewer_can_download_the_export(): void
    {
        $viewer = $this->viewer();
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();

        $assignment = Assignment::factory()->create([
            'job_period_id' => $period->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => '2025-09-02',
            'created_by' => $admin->id,
        ]);
        Attendance::factory()->create([
            'assignment_id' => $assignment->id,
            'tanggal' => '2025-09-01',
            'status' => 'hadir',
            'recorded_by' => $admin->id,
        ]);

        $response = $this->actingAs($viewer)->get(
            '/attendance/rekap/export?date_from=2025-09-01&date_to=2025-09-02'
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}

<?php

namespace Tests\Feature\Jobs;

use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_export(): void
    {
        $this->seed(RoleSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get('/jobs/export')->assertForbidden();
    }

    public function test_admin_can_export(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $job = Job::factory()->create();
        JobPeriod::factory()->create(['job_id' => $job->id]);

        $this->actingAs($admin)
            ->get('/jobs/export')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}

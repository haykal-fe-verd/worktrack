<?php

namespace Tests\Feature\Attendance;

use App\Exports\AttendanceRecapExport;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\JobPeriod;
use App\Models\User;
use App\Support\AttendanceRecap;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
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

    public function test_export_leaves_days_outside_the_assignment_range_blank_and_marks_unfilled_days_within_it(): void
    {
        $admin = $this->admin();
        $period = JobPeriod::factory()->create();

        // Assignment only overlaps 2025-09-02 through 2025-09-03 of the
        // requested 2025-09-01..2025-09-03 range; 2025-09-01 is outside it.
        $assignment = Assignment::factory()->create([
            'job_period_id' => $period->id,
            'tanggal_mulai' => '2025-09-02',
            'tanggal_selesai' => '2025-09-03',
            'created_by' => $admin->id,
        ]);
        Attendance::factory()->create([
            'assignment_id' => $assignment->id,
            'tanggal' => '2025-09-03',
            'status' => 'hadir',
            'recorded_by' => $admin->id,
        ]);

        $rows = AttendanceRecap::build('2025-09-01', '2025-09-03');
        $dateKeys = ['2025-09-01', '2025-09-02', '2025-09-03'];

        $export = new AttendanceRecapExport($rows, $dateKeys);
        $line = $export->collection()->first();

        // Columns: employee, job, no_dokumen, then one per date key.
        $this->assertSame('', $line[3], 'day before the assignment range should be blank, not "Belum Diisi"');
        $this->assertSame('Belum Diisi', $line[4], 'day within the assignment range with no record should be "Belum Diisi"');
        $this->assertSame('Hadir', $line[5]);
    }

    public function test_export_download_uses_the_scoped_rows_and_date_keys(): void
    {
        Excel::fake();

        $admin = $this->admin();
        $period = JobPeriod::factory()->create();

        Assignment::factory()->create([
            'job_period_id' => $period->id,
            'tanggal_mulai' => '2025-09-01',
            'tanggal_selesai' => '2025-09-02',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(
            '/attendance/rekap/export?date_from=2025-09-01&date_to=2025-09-02'
        );

        Excel::assertDownloaded('rekap-absensi-mingguan.xlsx', function (AttendanceRecapExport $export) {
            return $export->collection()->count() === 1;
        });
    }
}

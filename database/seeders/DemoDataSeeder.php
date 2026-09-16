<?php

namespace Database\Seeders;

use App\Enums\AssignmentStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed realistic, interconnected demo data across every menu
     * (Karyawan, Job & PR, Assignment, Absensi, Manajemen User) so a
     * freshly-provisioned environment isn't empty.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $demoPassword = Hash::make('password');

        $admin = User::firstOrCreate(
            ['email' => 'admin@worktrack.test'],
            ['name' => 'Admin Demo', 'email_verified_at' => now(), 'password' => $demoPassword],
        );
        $admin->syncRoles(['admin']);

        $staffInput = User::firstOrCreate(
            ['email' => 'staff@worktrack.test'],
            ['name' => 'Staff Input Demo', 'email_verified_at' => now(), 'password' => $demoPassword],
        );
        $staffInput->syncRoles(['staff_input']);

        $viewer = User::firstOrCreate(
            ['email' => 'viewer@worktrack.test'],
            ['name' => 'Viewer Demo', 'email_verified_at' => now(), 'password' => $demoPassword],
        );
        $viewer->syncRoles(['viewer']);

        $employees = Employee::factory()
            ->count(12)
            ->create(['status' => EmployeeStatus::Aktif]);
        Employee::factory()
            ->count(3)
            ->create(['status' => EmployeeStatus::NonAktif]);

        // Jobs ending soon (for the Dashboard's "mendekati berakhir" panel).
        $jobsEndingSoon = collect(range(1, 3))->map(function (int $i) {
            $job = Job::factory()->create(['status' => JobStatus::Aktif]);

            return JobPeriod::factory()->create([
                'job_id' => $job->id,
                'status' => JobPeriodStatus::Aktif,
                'tanggal_mulai' => now()->subMonths(2)->format('Y-m-d'),
                'tanggal_selesai' => now()->addDays(3 * $i)->format('Y-m-d'),
            ]);
        });

        // Jobs with plenty of runway left.
        $jobsOngoing = collect(range(1, 2))->map(function () {
            $job = Job::factory()->create(['status' => JobStatus::Aktif]);

            return JobPeriod::factory()->create([
                'job_id' => $job->id,
                'status' => JobPeriodStatus::Aktif,
                'tanggal_mulai' => now()->subMonth()->format('Y-m-d'),
                'tanggal_selesai' => now()->addMonths(3)->format('Y-m-d'),
            ]);
        });

        // A fully completed Job (for status-filter demo purposes).
        $completedJob = Job::factory()->create(['status' => JobStatus::Selesai]);
        JobPeriod::factory()->create([
            'job_id' => $completedJob->id,
            'status' => JobPeriodStatus::Berakhir,
            'tanggal_mulai' => now()->subMonths(6)->format('Y-m-d'),
            'tanggal_selesai' => now()->subMonth()->format('Y-m-d'),
        ]);

        $activePeriods = $jobsEndingSoon->concat($jobsOngoing);
        $employeePool = $employees->values();
        $employeeCursor = 0;

        $currentAssignments = collect();

        foreach ($activePeriods as $period) {
            $slots = fake()->numberBetween(2, 3);

            for ($i = 0; $i < $slots && $employeeCursor < $employeePool->count(); $i++) {
                $employee = $employeePool[$employeeCursor++];

                $currentAssignments->push(Assignment::factory()->create([
                    'employee_id' => $employee->id,
                    'job_period_id' => $period->id,
                    'status' => AssignmentStatus::Aktif,
                    'is_current' => true,
                    'tanggal_mulai' => $period->tanggal_mulai->format('Y-m-d'),
                    'tanggal_selesai' => null,
                    'created_by' => $admin->id,
                ]));
            }
        }

        // A finished assignment (employee already rolled off), for history depth.
        if ($employeeCursor < $employeePool->count()) {
            $endedEmployee = $employeePool[$employeeCursor++];
            Assignment::factory()->create([
                'employee_id' => $endedEmployee->id,
                'job_period_id' => $activePeriods->first()->id,
                'status' => AssignmentStatus::Selesai,
                'is_current' => true,
                'tanggal_mulai' => now()->subMonths(2)->format('Y-m-d'),
                'tanggal_selesai' => now()->subWeek()->format('Y-m-d'),
                'created_by' => $staffInput->id,
            ]);
        }

        // A superseded assignment with a successor (date-change versioning demo).
        if ($employeeCursor < $employeePool->count()) {
            $renewedEmployee = $employeePool[$employeeCursor++];
            $previous = Assignment::factory()->create([
                'employee_id' => $renewedEmployee->id,
                'job_period_id' => $activePeriods->last()->id,
                'status' => AssignmentStatus::Diperbarui,
                'is_current' => false,
                'tanggal_mulai' => now()->subMonths(2)->format('Y-m-d'),
                'tanggal_selesai' => now()->subMonth()->format('Y-m-d'),
                'created_by' => $staffInput->id,
            ]);
            $currentAssignments->push(Assignment::factory()->create([
                'employee_id' => $renewedEmployee->id,
                'job_period_id' => $activePeriods->last()->id,
                'status' => AssignmentStatus::Aktif,
                'is_current' => true,
                'previous_assignment_id' => $previous->id,
                'tanggal_mulai' => now()->subMonth()->addDay()->format('Y-m-d'),
                'tanggal_selesai' => null,
                'created_by' => $staffInput->id,
            ]));
        }

        // Attendance for the current week (Mon-Thu filled, Friday left
        // "belum diisi" on purpose so the Attendance/Rekap and Dashboard
        // demo something worth looking at).
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $statusRotation = [AttendanceStatus::Hadir, AttendanceStatus::Hadir, AttendanceStatus::Hadir, AttendanceStatus::Izin];

        foreach ($currentAssignments as $assignment) {
            for ($day = 0; $day < 4; $day++) {
                $date = $weekStart->copy()->addDays($day);

                if ($date->isFuture()) {
                    continue;
                }

                if ($date->lt($assignment->tanggal_mulai)) {
                    continue;
                }

                Attendance::factory()->create([
                    'assignment_id' => $assignment->id,
                    'tanggal' => $date->format('Y-m-d'),
                    'status' => $statusRotation[$day],
                    'recorded_by' => $staffInput->id,
                ]);
            }
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Job;
use App\Models\JobPeriod;
use App\Models\User;
use App\Support\AttendanceRecap;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with a per-role user count summary.
     */
    public function index(Request $request): Response
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $recap = AttendanceRecap::build($weekStart, $weekEnd, null, true);

        $belumDiisiCount = $recap->sum(fn (array $row) => collect($row['days'])->filter(fn (string $status) => $status === 'belum_diisi')->count());

        $attendanceSummary = [
            'hadir' => $recap->sum(fn (array $row) => $row['summary']['hadir']),
            'tidak_hadir' => $recap->sum(fn (array $row) => $row['summary']['tidak_hadir']),
            'izin' => $recap->sum(fn (array $row) => $row['summary']['izin']),
            'belum_diisi' => $belumDiisiCount,
        ];

        $upcomingJobPeriods = JobPeriod::query()
            ->where('status', JobPeriodStatus::Aktif)
            ->whereNotNull('tanggal_selesai')
            ->whereBetween('tanggal_selesai', [now()->format('Y-m-d'), now()->addDays(14)->format('Y-m-d')])
            ->with('job:id,nama_pekerjaan')
            ->orderBy('tanggal_selesai')
            ->limit(5)
            ->get()
            ->map(fn (JobPeriod $period) => [
                'id' => $period->id,
                'job_id' => $period->job_id,
                'job_nama_pekerjaan' => $period->job->nama_pekerjaan,
                'no_dokumen' => $period->no_dokumen,
                'tanggal_selesai' => $period->tanggal_selesai->format('Y-m-d'),
            ]);

        $recentEmployees = Employee::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'nama', 'status', 'created_at'])
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'nama' => $employee->nama,
                'status' => $employee->status->value,
                'created_at' => $employee->created_at->format('Y-m-d'),
            ]);

        $recentJobs = Job::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'nama_pekerjaan', 'status', 'created_at'])
            ->map(fn (Job $job) => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
                'status' => $job->status->value,
                'created_at' => $job->created_at->format('Y-m-d'),
            ]);

        return Inertia::render('Dashboard', [
            'employeeCount' => Employee::count(),
            'jobCount' => Job::where('status', JobStatus::Aktif)->count(),
            'assignmentCount' => Assignment::where('is_current', true)
                ->where('status', AssignmentStatus::Aktif)
                ->count(),
            'belumDiisiCount' => $belumDiisiCount,
            'roleCounts' => [
                'admin' => User::role(Role::Admin->value)->count(),
                'staff_input' => User::role(Role::StaffInput->value)->count(),
                'viewer' => User::role(Role::Viewer->value)->count(),
                'total' => User::count(),
            ],
            'canManage' => $request->user()->hasAnyRole(['admin', 'staff_input']),
            'isAdmin' => $request->user()->hasRole('admin'),
            'attendanceSummary' => $attendanceSummary,
            'upcomingJobPeriods' => $upcomingJobPeriods,
            'recentEmployees' => $recentEmployees,
            'recentJobs' => $recentJobs,
        ]);
    }
}

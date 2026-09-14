<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\Job;
use App\Models\User;
use App\Support\AttendanceRecap;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with a per-role user count summary.
     */
    public function index(): Response
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        $weekEnd = now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $belumDiisiCount = AttendanceRecap::build($weekStart, $weekEnd)
            ->sum(fn (array $row) => collect($row['days'])->filter(fn (string $status) => $status === 'belum_diisi')->count());

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
        ]);
    }
}

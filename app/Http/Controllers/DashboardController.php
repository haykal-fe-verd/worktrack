<?php

namespace App\Http\Controllers;

use App\Enums\JobStatus;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\Job;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with a per-role user count summary.
     */
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'employeeCount' => Employee::count(),
            'jobCount' => Job::where('status', JobStatus::Aktif)->count(),
            'roleCounts' => [
                'admin' => User::role(Role::Admin->value)->count(),
                'staff_input' => User::role(Role::StaffInput->value)->count(),
                'viewer' => User::role(Role::Viewer->value)->count(),
                'total' => User::count(),
            ],
        ]);
    }
}

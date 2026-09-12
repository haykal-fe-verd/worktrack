<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\EmployeeStatus;
use App\Enums\JobPeriodStatus;
use App\Http\Requests\StoreAssignmentRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentController extends Controller
{
    public function create(JobPeriod $jobPeriod): Response
    {
        abort_if($jobPeriod->status !== JobPeriodStatus::Aktif, 403);

        return Inertia::render('Assignments/Create', [
            'jobPeriod' => [
                'id' => $jobPeriod->id,
                'no_dokumen' => $jobPeriod->no_dokumen,
            ],
            'employees' => Employee::where('status', EmployeeStatus::Aktif)
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'nama' => $employee->nama,
                ]),
        ]);
    }

    public function store(StoreAssignmentRequest $request, JobPeriod $jobPeriod): RedirectResponse
    {
        abort_if($jobPeriod->status !== JobPeriodStatus::Aktif, 403);

        Assignment::create([
            'employee_id' => $request->validated('employee_id'),
            'job_period_id' => $jobPeriod->id,
            'tanggal_mulai' => $request->validated('tanggal_mulai'),
            'tanggal_selesai' => $request->validated('tanggal_selesai'),
            'tarif_jual' => $request->validated('tarif_jual'),
            'tarif_bayar' => $request->validated('tarif_bayar'),
            'status' => AssignmentStatus::Aktif,
            'is_current' => true,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('job-periods.show', $jobPeriod);
    }
}

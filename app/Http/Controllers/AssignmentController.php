<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\EmployeeStatus;
use App\Enums\JobPeriodStatus;
use App\Http\Requests\EndAssignmentRequest;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

        return redirect()->route('job-periods.show', $jobPeriod)->with('success', 'Karyawan berhasil ditugaskan.');
    }

    public function edit(Assignment $assignment): Response
    {
        abort_if(! $assignment->is_current || $assignment->status !== AssignmentStatus::Aktif, 404);

        return Inertia::render('Assignments/Edit', [
            'assignment' => [
                'id' => $assignment->id,
                'job_period_id' => $assignment->job_period_id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $assignment->tanggal_selesai?->format('Y-m-d'),
                'tarif_jual' => $assignment->tarif_jual,
                'tarif_bayar' => $assignment->tarif_bayar,
            ],
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        abort_if(! $assignment->is_current || $assignment->status !== AssignmentStatus::Aktif, 404);

        $data = $request->validated();

        $dateChanged = $data['tanggal_mulai'] !== $assignment->tanggal_mulai->format('Y-m-d')
            || ($data['tanggal_selesai'] ?? null) !== $assignment->tanggal_selesai?->format('Y-m-d');

        if ($dateChanged) {
            DB::transaction(function () use ($data, $assignment, $request) {
                $assignment->update([
                    'status' => AssignmentStatus::Diperbarui,
                    'is_current' => false,
                ]);

                Assignment::create([
                    'employee_id' => $assignment->employee_id,
                    'job_period_id' => $assignment->job_period_id,
                    'tanggal_mulai' => $data['tanggal_mulai'],
                    'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
                    'tarif_jual' => $data['tarif_jual'] ?? null,
                    'tarif_bayar' => $data['tarif_bayar'] ?? null,
                    'status' => AssignmentStatus::Aktif,
                    'is_current' => true,
                    'previous_assignment_id' => $assignment->id,
                    'created_by' => $request->user()->id,
                ]);
            });
        } else {
            $assignment->update([
                'tarif_jual' => $data['tarif_jual'] ?? null,
                'tarif_bayar' => $data['tarif_bayar'] ?? null,
            ]);
        }

        return redirect()->route('job-periods.show', $assignment->job_period_id)->with('success', 'Penugasan berhasil diperbarui.');
    }

    public function endForm(Assignment $assignment): Response
    {
        abort_if(! $assignment->is_current || $assignment->status !== AssignmentStatus::Aktif, 404);

        return Inertia::render('Assignments/End', [
            'assignment' => [
                'id' => $assignment->id,
                'job_period_id' => $assignment->job_period_id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
            ],
        ]);
    }

    public function end(EndAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        abort_if(! $assignment->is_current || $assignment->status !== AssignmentStatus::Aktif, 404);

        $assignment->update([
            'tanggal_selesai' => $request->validated('tanggal_selesai'),
            'status' => AssignmentStatus::Selesai,
        ]);

        return redirect()->route('job-periods.show', $assignment->job_period_id)->with('success', 'Penugasan berhasil diakhiri.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\JobPeriodStatus;
use App\Http\Requests\StoreJobPeriodRequest;
use App\Models\Assignment;
use App\Models\Job;
use App\Models\JobPeriod;
use App\Support\Masks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobPeriodController extends Controller
{
    public function create(Job $job): Response
    {
        return Inertia::render('Jobs/Periods/Create', [
            'job' => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
            ],
        ]);
    }

    public function store(StoreJobPeriodRequest $request, Job $job): RedirectResponse
    {
        DB::transaction(function () use ($request, $job) {
            $oldPeriod = $job->activePeriod;

            $job->periods()->create([
                ...$request->validated(),
                'status' => JobPeriodStatus::Aktif,
                'previous_period_id' => $oldPeriod?->id,
            ]);

            if ($oldPeriod) {
                $oldPeriod->update(['status' => JobPeriodStatus::Berakhir]);
            }
        });

        return redirect()->route('jobs.show', $job);
    }

    public function show(Request $request, JobPeriod $jobPeriod): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $assignments = $jobPeriod->assignments()->with('employee')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $activeCount = $assignments->where('is_current', true)->where('status', AssignmentStatus::Aktif)->count();

        return Inertia::render('JobPeriods/Show', [
            'jobPeriod' => [
                'id' => $jobPeriod->id,
                'job_id' => $jobPeriod->job_id,
                'job_nama_pekerjaan' => $jobPeriod->job->nama_pekerjaan,
                'jenis_dokumen' => $jobPeriod->jenis_dokumen->value,
                'no_dokumen' => $jobPeriod->no_dokumen,
                'tanggal_mulai' => $jobPeriod->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $jobPeriod->tanggal_selesai?->format('Y-m-d'),
                'jumlah_tk_rencana' => $jobPeriod->jumlah_tk_rencana,
                'status' => $jobPeriod->status->value,
            ],
            'assignments' => $assignments->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'employee_nama' => $assignment->employee->nama,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $assignment->tanggal_selesai?->format('Y-m-d'),
                'status' => $assignment->status->value,
                'is_current' => $assignment->is_current,
                'tarif_jual' => $assignment->tarif_jual === null
                    ? null
                    : ($canManage ? (string) $assignment->tarif_jual : Masks::partial((string) $assignment->tarif_jual)),
                'tarif_bayar' => $assignment->tarif_bayar === null
                    ? null
                    : ($canManage ? (string) $assignment->tarif_bayar : Masks::partial((string) $assignment->tarif_bayar)),
            ])->values(),
            'activeAssignmentCount' => $activeCount,
            'warningJumlahTk' => $activeCount !== $jobPeriod->jumlah_tk_rencana,
            'canManage' => $canManage,
        ]);
    }
}

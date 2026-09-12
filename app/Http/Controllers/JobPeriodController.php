<?php

namespace App\Http\Controllers;

use App\Enums\JobPeriodStatus;
use App\Http\Requests\StoreJobPeriodRequest;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
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
}

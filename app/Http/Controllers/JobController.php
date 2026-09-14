<?php

namespace App\Http\Controllers;

use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use App\Http\Requests\StoreJobRequest;
use App\Models\Job;
use App\Models\JobPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(Request $request): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $jobs = Job::query()
            ->withCount('periods')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($query) use ($search) {
                    $query->where('nama_pekerjaan', 'like', "%{$search}%")
                        ->orWhere('klien', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('nama_pekerjaan')
            ->paginate(20)
            ->withQueryString();

        $jobs->through(fn (Job $job) => [
            'id' => $job->id,
            'nama_pekerjaan' => $job->nama_pekerjaan,
            'klien' => $job->klien,
            'lokasi' => $job->lokasi,
            'status' => $job->status->value,
            'periods_count' => $job->periods_count,
        ]);

        return Inertia::render('Jobs/Index', [
            'jobs' => $jobs,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $canManage,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Jobs/Create');
    }

    public function store(StoreJobRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $job = Job::create([
                'nama_pekerjaan' => $request->validated('nama_pekerjaan'),
                'lokasi' => $request->validated('lokasi'),
                'klien' => $request->validated('klien'),
                'status' => JobStatus::Aktif,
            ]);

            $job->periods()->create([
                'jenis_dokumen' => $request->validated('jenis_dokumen'),
                'no_dokumen' => $request->validated('no_dokumen'),
                'kode_po' => $request->validated('kode_po'),
                'nilai_po' => $request->validated('nilai_po'),
                'tanggal_mulai' => $request->validated('tanggal_mulai'),
                'tanggal_selesai' => $request->validated('tanggal_selesai'),
                'jumlah_tk_rencana' => $request->validated('jumlah_tk_rencana'),
                'keterangan' => $request->validated('keterangan'),
                'status' => JobPeriodStatus::Aktif,
            ]);
        });

        return redirect()->route('jobs.index')->with('success', 'Job berhasil ditambahkan.');
    }

    public function show(Request $request, Job $job): Response
    {
        $periods = $job->periods()->orderByDesc('tanggal_mulai')->get();

        return Inertia::render('Jobs/Show', [
            'job' => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
                'lokasi' => $job->lokasi,
                'klien' => $job->klien,
                'status' => $job->status->value,
            ],
            'periods' => $periods->map(fn (JobPeriod $period) => [
                'id' => $period->id,
                'jenis_dokumen' => $period->jenis_dokumen->value,
                'no_dokumen' => $period->no_dokumen,
                'kode_po' => $period->kode_po,
                'nilai_po' => (float) $period->nilai_po,
                'tanggal_mulai' => $period->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $period->tanggal_selesai?->format('Y-m-d'),
                'jumlah_tk_rencana' => $period->jumlah_tk_rencana,
                'status' => $period->status->value,
            ])->values(),
            'hasActivePeriod' => $job->activePeriod()->exists(),
            'canManage' => $request->user()->hasAnyRole(['admin', 'staff_input']),
        ]);
    }

    public function renew(Job $job): Response
    {
        return Inertia::render('Jobs/Renew', [
            'job' => [
                'id' => $job->id,
                'nama_pekerjaan' => $job->nama_pekerjaan,
            ],
            'activePeriodId' => $job->activePeriod?->id,
        ]);
    }

    public function toggleStatus(Job $job): RedirectResponse
    {
        $job->update([
            'status' => $job->status === JobStatus::Aktif ? JobStatus::Selesai : JobStatus::Aktif,
        ]);

        return redirect()->route('jobs.show', $job);
    }
}

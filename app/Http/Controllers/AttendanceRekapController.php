<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceRecapExport;
use App\Models\Job;
use App\Support\AttendanceRecap;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceRekapController extends Controller
{
    public function index(Request $request): Response
    {
        $this->validateFilters($request);

        [$dateFrom, $dateTo, $jobId] = $this->resolveFilters($request);

        $rows = AttendanceRecap::build($dateFrom, $dateTo, $jobId)->values();
        $dateKeys = $this->buildDateKeys($dateFrom, $dateTo);

        $perPage = 20;
        $page = Paginator::resolveCurrentPage();

        $paginatedRows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Attendance/Rekap', [
            'jobs' => Job::orderBy('nama_pekerjaan')->get(['id', 'nama_pekerjaan']),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'job_id' => $jobId,
            ],
            'dateKeys' => $dateKeys,
            'rows' => $paginatedRows,
            'canManage' => $request->user()->hasAnyRole(['admin', 'staff_input']),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->validateFilters($request);

        [$dateFrom, $dateTo, $jobId] = $this->resolveFilters($request);

        $rows = AttendanceRecap::build($dateFrom, $dateTo, $jobId);
        $dateKeys = $this->buildDateKeys($dateFrom, $dateTo);

        return Excel::download(
            new AttendanceRecapExport($rows, $dateKeys),
            'rekap-absensi-mingguan.xlsx'
        );
    }

    /**
     * Validate the optional date_from/date_to/job_id query parameters.
     *
     * Only validates when the parameters are present, since both routes
     * fall back to the current week when they're omitted entirely.
     */
    private function validateFilters(Request $request): void
    {
        $request->validate([
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
            'job_id' => ['sometimes', 'nullable', 'integer', 'exists:client_jobs,id'],
        ]);

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $span = Carbon::parse($request->query('date_from'))
                ->diffInDays(Carbon::parse($request->query('date_to')));

            if ($span > 366) {
                throw ValidationException::withMessages([
                    'date_to' => 'Rentang tanggal tidak boleh lebih dari 366 hari.',
                ]);
            }
        }
    }

    /**
     * Resolve and return filter values from request with sensible defaults.
     *
     * @return array{0: string, 1: string, 2: int|null}
     */
    private function resolveFilters(Request $request): array
    {
        $dateFrom = $request->filled('date_from')
            ? $request->query('date_from')
            : now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');

        $dateTo = $request->filled('date_to')
            ? $request->query('date_to')
            : now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $jobId = $request->filled('job_id') ? (int) $request->query('job_id') : null;

        return [$dateFrom, $dateTo, $jobId];
    }

    /**
     * Build array of date strings spanning the given range.
     *
     * @return array<int, string>
     */
    private function buildDateKeys(string $dateFrom, string $dateTo): array
    {
        $dateKeys = [];

        for ($day = Carbon::parse($dateFrom)->startOfDay(); $day->lte(Carbon::parse($dateTo)->startOfDay()); $day->addDay()) {
            $dateKeys[] = $day->format('Y-m-d');
        }

        return $dateKeys;
    }
}

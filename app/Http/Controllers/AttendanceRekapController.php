<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceRecapExport;
use App\Models\Job;
use App\Support\AttendanceRecap;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceRekapController extends Controller
{
    public function index(Request $request): Response
    {
        [$dateFrom, $dateTo, $jobId] = $this->resolveFilters($request);

        $rows = AttendanceRecap::build($dateFrom, $dateTo, $jobId);
        $dateKeys = $this->buildDateKeys($dateFrom, $dateTo);

        return Inertia::render('Attendance/Rekap', [
            'jobs' => Job::orderBy('nama_pekerjaan')->get(['id', 'nama_pekerjaan']),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'job_id' => $jobId,
            ],
            'dateKeys' => $dateKeys,
            'rows' => $rows->values(),
            'canManage' => $request->user()->hasAnyRole(['admin', 'staff_input']),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        [$dateFrom, $dateTo, $jobId] = $this->resolveFilters($request);

        $rows = AttendanceRecap::build($dateFrom, $dateTo, $jobId);
        $dateKeys = $this->buildDateKeys($dateFrom, $dateTo);

        return Excel::download(
            new AttendanceRecapExport($rows, $dateKeys),
            'rekap-absensi-mingguan.xlsx'
        );
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

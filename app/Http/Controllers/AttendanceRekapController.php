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
        $dateFrom = $request->filled('date_from')
            ? $request->query('date_from')
            : now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');

        $dateTo = $request->filled('date_to')
            ? $request->query('date_to')
            : now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $jobId = $request->filled('job_id') ? (int) $request->query('job_id') : null;

        $rows = AttendanceRecap::build($dateFrom, $dateTo, $jobId);

        return Inertia::render('Attendance/Rekap', [
            'jobs' => Job::orderBy('nama_pekerjaan')->get(['id', 'nama_pekerjaan']),
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'job_id' => $jobId,
            ],
            'rows' => $rows->values(),
            'canManage' => $request->user()->hasAnyRole(['admin', 'staff_input']),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $dateFrom = $request->filled('date_from')
            ? $request->query('date_from')
            : now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');

        $dateTo = $request->filled('date_to')
            ? $request->query('date_to')
            : now()->endOfWeek(Carbon::SUNDAY)->format('Y-m-d');

        $jobId = $request->filled('job_id') ? (int) $request->query('job_id') : null;

        $rows = AttendanceRecap::build($dateFrom, $dateTo, $jobId);

        $dateKeys = [];

        for ($day = Carbon::parse($dateFrom)->startOfDay(); $day->lte(Carbon::parse($dateTo)->startOfDay()); $day->addDay()) {
            $dateKeys[] = $day->format('Y-m-d');
        }

        return Excel::download(
            new AttendanceRecapExport($rows, $dateKeys),
            'rekap-absensi-mingguan.xlsx'
        );
    }
}

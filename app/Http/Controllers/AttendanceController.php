<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\JobStatus;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function input(Request $request): Response
    {
        $jobs = Job::where('status', JobStatus::Aktif)
            ->orderBy('nama_pekerjaan')
            ->get(['id', 'nama_pekerjaan']);

        $weekStart = $request->filled('week_start')
            ? Carbon::parse($request->query('week_start'))->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);

        $weekDates = collect(range(0, 6))
            ->map(fn (int $i) => $weekStart->copy()->addDays($i)->format('Y-m-d'))
            ->values();

        $selectedJobId = $request->filled('job_id') ? (int) $request->query('job_id') : null;
        $hasActivePeriod = false;
        $rows = [];

        if ($selectedJobId) {
            $job = Job::find($selectedJobId);
            $activePeriod = $job?->activePeriod;

            if ($activePeriod) {
                $hasActivePeriod = true;
                $today = now()->startOfDay();

                $assignments = Assignment::where('job_period_id', $activePeriod->id)
                    ->where('is_current', true)
                    ->with(['employee', 'attendances' => function ($query) use ($weekDates) {
                        $query->whereIn('tanggal', $weekDates);
                    }])
                    ->get();

                $rows = $assignments->map(function (Assignment $assignment) use ($weekDates, $today) {
                    $attendanceByDate = $assignment->attendances->keyBy(fn ($a) => $a->tanggal->format('Y-m-d'));
                    $effectiveEnd = $assignment->tanggal_selesai ?? $today;

                    $cells = $weekDates->map(function (string $date) use ($assignment, $effectiveEnd, $today, $attendanceByDate) {
                        $day = Carbon::parse($date);
                        $disabled = $day->lt($assignment->tanggal_mulai)
                            || $day->gt($effectiveEnd)
                            || $day->gt($today)
                            || ! in_array($assignment->status, [AssignmentStatus::Aktif, AssignmentStatus::Diperbarui], true);

                        $attendance = $attendanceByDate->get($date);

                        return [
                            'tanggal' => $date,
                            'disabled' => $disabled,
                            'status' => $attendance?->status->value,
                            'catatan' => $attendance?->catatan,
                        ];
                    })->values();

                    return [
                        'assignment_id' => $assignment->id,
                        'employee_nama' => $assignment->employee->nama,
                        'cells' => $cells,
                    ];
                })->values();
            }
        }

        return Inertia::render('Attendance/Input', [
            'jobs' => $jobs,
            'selectedJobId' => $selectedJobId,
            'weekStart' => $weekStart->format('Y-m-d'),
            'weekDates' => $weekDates,
            'hasActivePeriod' => $hasActivePeriod,
            'rows' => $rows,
        ]);
    }

    public function store(StoreAttendanceRequest $request): RedirectResponse
    {
        // Matched with whereDate() rather than Attendance::updateOrCreate()'s
        // raw attribute match: the 'tanggal' column is cast to a Carbon date,
        // which Eloquent serializes with a time component ("Y-m-d 00:00:00")
        // when persisting. Under sqlite (used by the test suite) that string
        // is stored verbatim, so a plain string comparison against the
        // request's "Y-m-d" value would miss the existing row and attempt a
        // duplicate insert, violating the (assignment_id, tanggal) unique
        // constraint. whereDate() matches on the date part on every driver.
        $attendance = Attendance::where('assignment_id', $request->validated('assignment_id'))
            ->whereDate('tanggal', $request->validated('tanggal'))
            ->first() ?? new Attendance([
                'assignment_id' => $request->validated('assignment_id'),
                'tanggal' => $request->validated('tanggal'),
            ]);

        $attendance->status = $request->validated('status');
        $attendance->catatan = $request->validated('catatan');
        $attendance->recorded_by = $request->user()->id;
        $attendance->save();

        return back()->with('success', 'Absensi berhasil disimpan.');
    }
}

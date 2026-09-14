<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Enums\JobPeriodStatus;
use App\Models\Assignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceRecap
{
    /**
     * Build per-assignment attendance rows for the given date range,
     * marking any day within the assignment's active range that has
     * no Attendance record as "belum_diisi".
     *
     * When $onlyFillable is true, the query is additionally scoped to
     * assignments that are actually fillable from the Input Absensi screen
     * right now: is_current, an Aktif/Diperbarui status, and a JobPeriod
     * that is currently Aktif. Callers that want the full report history
     * (the Rekap Mingguan page/export) must leave this false.
     *
     * @return Collection<int, array{
     *     assignment_id: int,
     *     employee_nama: string,
     *     job_nama_pekerjaan: string,
     *     no_dokumen: string,
     *     days: array<string, string>,
     *     summary: array{hadir: int, tidak_hadir: int, izin: int},
     * }>
     */
    public static function build(string $dateFrom, string $dateTo, ?int $jobId = null, bool $onlyFillable = false): Collection
    {
        $rangeStart = Carbon::parse($dateFrom)->startOfDay();
        $rangeEnd = Carbon::parse($dateTo)->startOfDay();
        $today = now()->startOfDay();

        // Note: whereDate() (not a raw string comparison) is required here
        // because the 'date'-cast columns involved (Attendance::tanggal,
        // Assignment::tanggal_mulai/tanggal_selesai) are persisted with a
        // "Y-m-d 00:00:00" time component under sqlite (the test suite's
        // driver) — see the matching comment in AttendanceController::store().
        // A plain string comparison against a "Y-m-d" bound would silently
        // exclude rows that should match.
        $assignments = Assignment::query()
            ->with(['employee', 'jobPeriod.job', 'attendances' => function ($query) use ($rangeStart, $rangeEnd) {
                $query->whereDate('tanggal', '>=', $rangeStart->format('Y-m-d'))
                    ->whereDate('tanggal', '<=', $rangeEnd->format('Y-m-d'));
            }])
            ->whereDate('tanggal_mulai', '<=', $rangeEnd->format('Y-m-d'))
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $rangeStart->format('Y-m-d'));
            })
            ->when($jobId, fn ($query) => $query->whereHas(
                'jobPeriod',
                fn ($q) => $q->where('job_id', $jobId)
            ))
            ->when($onlyFillable, fn ($query) => $query
                ->where('is_current', true)
                ->whereIn('status', [AssignmentStatus::Aktif, AssignmentStatus::Diperbarui])
                ->whereHas('jobPeriod', fn ($q) => $q->where('status', JobPeriodStatus::Aktif))
            )
            ->get();

        return $assignments
            ->map(function (Assignment $assignment) use ($rangeStart, $rangeEnd, $today) {
                $effectiveEnd = $today->min($assignment->tanggal_selesai ?? $today);
                $overlapStart = $assignment->tanggal_mulai->max($rangeStart);
                $overlapEnd = $effectiveEnd->min($rangeEnd);

                if ($overlapStart->gt($overlapEnd)) {
                    return null;
                }

                $attendanceByDate = $assignment->attendances->keyBy(
                    fn ($attendance) => $attendance->tanggal->format('Y-m-d')
                );

                $days = [];
                $summary = ['hadir' => 0, 'tidak_hadir' => 0, 'izin' => 0];

                for ($day = $overlapStart->copy(); $day->lte($overlapEnd); $day->addDay()) {
                    $dateKey = $day->format('Y-m-d');
                    $attendance = $attendanceByDate->get($dateKey);
                    $days[$dateKey] = $attendance?->status->value ?? 'belum_diisi';

                    if ($attendance) {
                        $summary[$attendance->status->value]++;
                    }
                }

                return [
                    'assignment_id' => $assignment->id,
                    'employee_nama' => $assignment->employee->nama,
                    'job_nama_pekerjaan' => $assignment->jobPeriod->job->nama_pekerjaan,
                    'no_dokumen' => $assignment->jobPeriod->no_dokumen,
                    'days' => $days,
                    'summary' => $summary,
                ];
            })
            ->filter()
            ->values();
    }
}

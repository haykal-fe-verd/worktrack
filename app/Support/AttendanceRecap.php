<?php

namespace App\Support;

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
     * @return Collection<int, array{
     *     assignment_id: int,
     *     employee_nama: string,
     *     job_nama_pekerjaan: string,
     *     no_dokumen: string,
     *     days: array<string, string>,
     *     summary: array{hadir: int, tidak_hadir: int, izin: int},
     * }>
     */
    public static function build(string $dateFrom, string $dateTo, ?int $jobId = null): Collection
    {
        $rangeStart = Carbon::parse($dateFrom)->startOfDay();
        $rangeEnd = Carbon::parse($dateTo)->startOfDay();
        $today = now()->startOfDay();

        $assignments = Assignment::query()
            ->with(['employee', 'jobPeriod.job', 'attendances'])
            ->when($jobId, fn ($query) => $query->whereHas(
                'jobPeriod',
                fn ($q) => $q->where('job_id', $jobId)
            ))
            ->get();

        return $assignments
            ->map(function (Assignment $assignment) use ($rangeStart, $rangeEnd, $today) {
                $effectiveEnd = $assignment->tanggal_selesai ?? $today;
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

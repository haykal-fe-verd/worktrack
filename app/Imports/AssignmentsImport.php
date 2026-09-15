<?php

namespace App\Imports;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Employee;
use App\Models\JobPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

class AssignmentsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, string>> */
    public array $errors = [];

    public int $created = 0;

    public function __construct(
        private readonly int $userId,
    ) {}

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nik = trim((string) ($row['nik'] ?? ''));
            $noDokumen = trim((string) ($row['no_dokumen'] ?? ''));
            $tanggalMulaiRaw = $row['tanggal_mulai'] ?? null;
            $tanggalSelesaiRaw = $row['tanggal_selesai'] ?? null;

            if (! preg_match('/^\d{16}$/', $nik)) {
                $this->recordError($row, 'NIK harus 16 digit angka');

                continue;
            }

            $employee = Employee::where('nik_hash', Employee::hashNik($nik))->first();

            if (! $employee) {
                $this->recordError($row, 'NIK tidak terdaftar');

                continue;
            }

            if ($noDokumen === '') {
                $this->recordError($row, 'No. Dokumen wajib diisi');

                continue;
            }

            $jobPeriod = JobPeriod::where('no_dokumen', $noDokumen)->first();

            if (! $jobPeriod) {
                $this->recordError($row, 'No. Dokumen tidak terdaftar');

                continue;
            }

            $tanggalMulai = $this->parseDate($tanggalMulaiRaw);

            if ($tanggalMulai === null) {
                $this->recordError($row, 'Tanggal Mulai wajib diisi dengan format tanggal yang valid');

                continue;
            }

            $tanggalSelesai = null;

            if ($tanggalSelesaiRaw !== null && trim((string) $tanggalSelesaiRaw) !== '') {
                $tanggalSelesai = $this->parseDate($tanggalSelesaiRaw);

                if ($tanggalSelesai === null) {
                    $this->recordError($row, 'Tanggal Selesai format tidak valid');

                    continue;
                }

                if (Carbon::parse($tanggalSelesai)->lt(Carbon::parse($tanggalMulai))) {
                    $this->recordError($row, 'Tanggal Selesai harus setelah atau sama dengan Tanggal Mulai');

                    continue;
                }
            }

            $exists = Assignment::where('employee_id', $employee->id)
                ->where('job_period_id', $jobPeriod->id)
                ->exists();

            if ($exists) {
                $this->recordError($row, 'Karyawan sudah punya penugasan pada periode ini');

                continue;
            }

            $isPastEnd = $tanggalSelesai !== null
                && Carbon::parse($tanggalSelesai)->lt(Carbon::today());

            Assignment::create([
                'employee_id' => $employee->id,
                'job_period_id' => $jobPeriod->id,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
                'status' => $isPastEnd ? AssignmentStatus::Selesai : AssignmentStatus::Aktif,
                'is_current' => true,
                'previous_assignment_id' => null,
                'tarif_jual' => $this->parseAmount($row['tarif_jual'] ?? null),
                'tarif_bayar' => $this->parseAmount($row['tarif_bayar'] ?? null),
                'created_by' => $this->userId,
            ]);

            $this->created++;
        }
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return null;
        }

        $normalized = str_replace('.', '', $stringValue);
        $normalized = str_replace(',', '.', $normalized);

        return (float) preg_replace('/[^0-9.\-]/', '', $normalized);
    }

    /**
     * @param  Collection<string, mixed>  $row
     */
    private function recordError(Collection $row, string $reason): void
    {
        $this->errors[] = [
            'NIK' => (string) ($row['nik'] ?? ''),
            'NO. DOKUMEN' => (string) ($row['no_dokumen'] ?? ''),
            'TANGGAL MULAI' => (string) ($row['tanggal_mulai'] ?? ''),
            'TANGGAL SELESAI' => (string) ($row['tanggal_selesai'] ?? ''),
            'TARIF JUAL' => (string) ($row['tarif_jual'] ?? ''),
            'TARIF BAYAR' => (string) ($row['tarif_bayar'] ?? ''),
            'Alasan Gagal' => $reason,
        ];
    }
}

<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttendanceRecapExport implements FromCollection, WithHeadings
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $dateKeys
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly array $dateKeys,
    ) {}

    /**
     * @return Collection<int, array<int, string|int>>
     */
    public function collection(): Collection
    {
        return $this->rows->map(function (array $row) {
            $line = [
                $row['employee_nama'],
                $row['job_nama_pekerjaan'],
                $row['no_dokumen'],
            ];

            foreach ($this->dateKeys as $date) {
                $line[] = self::label($row['days'][$date] ?? null);
            }

            $line[] = $row['summary']['hadir'];
            $line[] = $row['summary']['tidak_hadir'];
            $line[] = $row['summary']['izin'];

            return $line;
        });
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $headings = ['Karyawan', 'Job', 'No. Dokumen'];

        foreach ($this->dateKeys as $date) {
            $headings[] = $date;
        }

        $headings[] = 'Hadir';
        $headings[] = 'Tidak Hadir';
        $headings[] = 'Izin';

        return $headings;
    }

    private static function label(?string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir',
            'tidak_hadir' => 'Tidak Hadir',
            'izin' => 'Izin',
            default => 'Belum Diisi',
        };
    }
}

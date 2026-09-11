<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeesImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, string>> */
    public array $errors = [];

    public int $created = 0;

    public int $skipped = 0;

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $nama = trim((string) ($row['nama'] ?? ''));
            $nik = trim((string) ($row['nik'] ?? ''));
            $alamat = trim((string) ($row['alamat'] ?? ''));
            $noRekening = trim((string) ($row['no_rekening'] ?? ''));

            $reason = $this->validateRow($nama, $nik, $alamat, $noRekening);

            if ($reason !== null) {
                $this->recordError($nama, $nik, $alamat, $noRekening, $reason);

                continue;
            }

            $existing = Employee::where('nik', $nik)->first();

            if ($existing) {
                if ($existing->nama === $nama) {
                    $this->skipped++;

                    continue;
                }

                $this->recordError(
                    $nama,
                    $nik,
                    $alamat,
                    $noRekening,
                    "NIK sudah terdaftar atas nama lain: {$existing->nama}",
                );

                continue;
            }

            Employee::create([
                'nama' => $nama,
                'nik' => $nik,
                'alamat' => $alamat,
                'no_rekening' => $noRekening,
                'status' => 'aktif',
            ]);

            $this->created++;
        }
    }

    private function validateRow(string $nama, string $nik, string $alamat, string $noRekening): ?string
    {
        if ($nama === '') {
            return 'Nama wajib diisi';
        }

        if (! preg_match('/^\d{16}$/', $nik)) {
            return 'NIK harus 16 digit angka';
        }

        if ($alamat === '') {
            return 'Alamat wajib diisi';
        }

        if ($noRekening === '' || ! preg_match('/^\d+$/', $noRekening)) {
            return 'No. Rekening wajib diisi dan hanya boleh angka';
        }

        return null;
    }

    private function recordError(string $nama, string $nik, string $alamat, string $noRekening, string $reason): void
    {
        $this->errors[] = [
            'NAMA' => $nama,
            'NIK' => $nik,
            'ALAMAT' => $alamat,
            'NO REKENING' => $noRekening,
            'Alasan Gagal' => $reason,
        ];
    }
}

<?php

namespace App\Imports;

use App\Enums\JobPeriodStatus;
use App\Enums\JobStatus;
use App\Models\Job;
use App\Models\JobPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Throwable;

class JobsImport implements ToCollection, WithHeadingRow
{
    /** @var array<int, array<string, string>> */
    public array $errors = [];

    public int $created = 0;

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $rawDocument = trim((string) ($row['no_doprwo'] ?? ''));
            $uraian = trim((string) ($row['uraian_pekerjaan'] ?? ''));
            $jumlahTkRaw = trim((string) ($row['jumlah_tk'] ?? ''));
            $kodePo = trim((string) ($row['po'] ?? ''));
            $nilaiPoRaw = trim((string) ($row['nilai_po'] ?? ''));
            $keterangan = trim((string) ($row['keterangan'] ?? ''));

            $parsed = $this->parseDocument($rawDocument);

            if ($parsed === null) {
                $this->recordError($row, 'Format No. Dokumen tidak dikenali');

                continue;
            }

            if ($uraian === '') {
                $this->recordError($row, 'Uraian Pekerjaan wajib diisi');

                continue;
            }

            $jumlahTk = (int) preg_replace('/\D/', '', $jumlahTkRaw);

            if ($jumlahTk < 1) {
                $this->recordError($row, 'Jumlah TK wajib diisi dan berupa angka');

                continue;
            }

            $tanggalMulai = $this->parseDate($row['mulai_tanggal'] ?? null);

            if ($tanggalMulai === null) {
                $this->recordError($row, 'Mulai Tanggal wajib diisi dengan format tanggal yang valid');

                continue;
            }

            [$jenisDokumen, $noDokumen] = $parsed;

            if (JobPeriod::where('no_dokumen', $noDokumen)->exists()) {
                $this->recordError($row, 'No. Dokumen sudah terdaftar');

                continue;
            }

            $job = Job::create([
                'nama_pekerjaan' => $uraian,
                'status' => JobStatus::Aktif,
            ]);

            $job->periods()->create([
                'jenis_dokumen' => $jenisDokumen,
                'no_dokumen' => $noDokumen,
                'kode_po' => $kodePo !== '' ? $kodePo : null,
                'nilai_po' => $this->parseIndonesianNumber($nilaiPoRaw),
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $this->parseDate($row['sd_tanggal'] ?? null),
                'jumlah_tk_rencana' => $jumlahTk,
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'status' => JobPeriodStatus::Aktif,
            ]);

            $this->created++;
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseDocument(string $value): ?array
    {
        foreach (['PR', 'DO', 'WO'] as $type) {
            if (preg_match('/'.$type.':\s*(\S+)/i', $value, $matches)) {
                return [$type, $matches[1]];
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Parse an Indonesian-formatted number ("6.812.604" or "1.234.567,89")
     * into a float. Indonesian notation uses "." as the thousands
     * separator and "," as the decimal separator — the reverse of
     * English notation.
     */
    private function parseIndonesianNumber(string $value): float
    {
        $normalized = str_replace('.', '', $value);
        $normalized = str_replace(',', '.', $normalized);

        return (float) preg_replace('/[^0-9.\-]/', '', $normalized);
    }

    /**
     * @param  Collection<string, mixed>  $row
     */
    private function recordError(Collection $row, string $reason): void
    {
        $this->errors[] = [
            'NO. DO/PR/WO' => (string) ($row['no_doprwo'] ?? ''),
            'URAIAN PEKERJAAN' => (string) ($row['uraian_pekerjaan'] ?? ''),
            'JUMLAH TK' => (string) ($row['jumlah_tk'] ?? ''),
            'MULAI TANGGAL' => (string) ($row['mulai_tanggal'] ?? ''),
            'S/D TANGGAL' => (string) ($row['sd_tanggal'] ?? ''),
            'PO' => (string) ($row['po'] ?? ''),
            'NILAI PO' => (string) ($row['nilai_po'] ?? ''),
            'KETERANGAN' => (string) ($row['keterangan'] ?? ''),
            'Alasan Gagal' => $reason,
        ];
    }
}

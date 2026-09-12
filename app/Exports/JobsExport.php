<?php

namespace App\Exports;

use App\Models\JobPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JobsExport implements FromCollection, WithHeadings
{
    /**
     * @return Collection<int, array<int, string|int|float|null>>
     */
    public function collection(): Collection
    {
        return JobPeriod::query()
            ->with('job')
            ->orderBy('job_id')
            ->get()
            ->map(fn (JobPeriod $period) => [
                $period->job->nama_pekerjaan,
                $period->jenis_dokumen->value,
                $period->no_dokumen,
                $period->kode_po,
                (float) $period->nilai_po,
                $period->tanggal_mulai->format('Y-m-d'),
                $period->tanggal_selesai?->format('Y-m-d'),
                $period->jumlah_tk_rencana,
                $period->status->value,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Nama Pekerjaan',
            'Jenis Dokumen',
            'No. Dokumen',
            'Kode PO',
            'Nilai PO',
            'Tanggal Mulai',
            'Tanggal Selesai',
            'Jumlah TK Rencana',
            'Status',
        ];
    }
}

<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportErrorsExport implements FromCollection, WithHeadings
{
    /**
     * @param  Collection<int, array<string, string>>  $errors
     */
    public function __construct(private readonly Collection $errors) {}

    public function collection(): Collection
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['NAMA', 'NIK', 'ALAMAT', 'NO REKENING', 'Alasan Gagal'];
    }
}

<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ImportErrorsExport implements FromCollection, WithHeadings
{
    /**
     * @param  Collection<int, array<string, string>>  $errors
     * @param  array<int, string>  $headings
     */
    public function __construct(
        private readonly Collection $errors,
        private readonly array $headings,
    ) {}

    public function collection(): Collection
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->headings;
    }
}

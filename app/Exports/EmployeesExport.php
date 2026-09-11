<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesExport implements FromCollection, WithHeadings
{
    /**
     * @return Collection<int, array<int, string|null>>
     */
    public function collection(): Collection
    {
        return Employee::query()
            ->orderBy('nama')
            ->get()
            ->map(fn (Employee $employee) => [
                $employee->nama,
                $employee->nik,
                $employee->alamat,
                $employee->no_rekening,
                $employee->nama_bank,
                $employee->status->value,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Nama', 'NIK', 'Alamat', 'No. Rekening', 'Nama Bank', 'Status'];
    }
}

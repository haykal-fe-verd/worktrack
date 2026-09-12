<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorsExport;
use App\Imports\EmployeesImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Employees/Import', [
            'result' => session('employee_import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new EmployeesImport;
        Excel::import($import, $request->file('file'));

        session(['employee_import_errors' => $import->errors]);

        return redirect()->route('employees.import.create')
            ->with('employee_import_result', [
                'created' => $import->created,
                'skipped' => $import->skipped,
                'errorCount' => count($import->errors),
            ])
            ->with('success', "Import selesai: {$import->created} berhasil, {$import->skipped} dilewati, ".count($import->errors).' gagal.');
    }

    public function downloadErrors(): BinaryFileResponse
    {
        $errors = session('employee_import_errors', []);

        abort_if($errors === [], 404);

        session()->forget('employee_import_errors');

        return Excel::download(
            new ImportErrorsExport(collect($errors), ['NAMA', 'NIK', 'ALAMAT', 'NO REKENING', 'Alasan Gagal']),
            'laporan-error-import-karyawan.xlsx',
        );
    }
}

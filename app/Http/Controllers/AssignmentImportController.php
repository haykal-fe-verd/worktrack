<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorsExport;
use App\Imports\AssignmentsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssignmentImportController extends Controller
{
    private const HEADINGS = [
        'NIK',
        'NO. DOKUMEN',
        'TANGGAL MULAI',
        'TANGGAL SELESAI',
        'TARIF JUAL',
        'TARIF BAYAR',
        'Alasan Gagal',
    ];

    public function create(): Response
    {
        return Inertia::render('Assignments/Import', [
            'result' => session('assignment_import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new AssignmentsImport($request->user()->id);
        Excel::import($import, $request->file('file'));

        session(['assignment_import_errors' => $import->errors]);

        $errorCount = count($import->errors);

        $response = redirect()->route('assignments.import.create')
            ->with('assignment_import_result', [
                'created' => $import->created,
                'errorCount' => $errorCount,
            ]);

        if ($errorCount > 0) {
            return $response->with('error', "Import selesai dengan {$errorCount} baris gagal ({$import->created} berhasil). Lihat laporan error untuk detail.");
        }

        return $response->with('success', "Import selesai: {$import->created} berhasil, {$errorCount} gagal.");
    }

    public function downloadErrors(): BinaryFileResponse
    {
        $errors = session('assignment_import_errors', []);

        abort_if($errors === [], 404);

        session()->forget('assignment_import_errors');

        return Excel::download(
            new ImportErrorsExport(collect($errors), self::HEADINGS),
            'laporan-error-import-penugasan.xlsx',
        );
    }
}

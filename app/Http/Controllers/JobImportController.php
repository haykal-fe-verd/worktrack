<?php

namespace App\Http\Controllers;

use App\Exports\ImportErrorsExport;
use App\Imports\JobsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobImportController extends Controller
{
    private const HEADINGS = [
        'NO. DO/PR/WO',
        'URAIAN PEKERJAAN',
        'JUMLAH TK',
        'MULAI TANGGAL',
        'S/D TANGGAL',
        'PO',
        'NILAI PO',
        'KETERANGAN',
        'Alasan Gagal',
    ];

    public function create(): Response
    {
        return Inertia::render('Jobs/Import', [
            'result' => session('job_import_result'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ]);

        $import = new JobsImport;
        Excel::import($import, $request->file('file'));

        session(['job_import_errors' => $import->errors]);

        $errorCount = count($import->errors);

        $response = redirect()->route('jobs.import.create')
            ->with('job_import_result', [
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
        $errors = session('job_import_errors', []);

        abort_if($errors === [], 404);

        session()->forget('job_import_errors');

        return Excel::download(
            new ImportErrorsExport(collect($errors), self::HEADINGS),
            'laporan-error-import-job.xlsx',
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\JobsExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobExportController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return Excel::download(new JobsExport, 'data-job-periode-pr.xlsx');
    }
}

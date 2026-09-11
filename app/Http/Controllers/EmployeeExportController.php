<?php

namespace App\Http\Controllers;

use App\Exports\EmployeesExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeExportController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return Excel::download(new EmployeesExport, 'data-karyawan.xlsx');
    }
}

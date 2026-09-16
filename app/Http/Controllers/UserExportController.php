<?php

namespace App\Http\Controllers;

use App\Exports\UsersExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserExportController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return Excel::download(new UsersExport, 'data-user.xlsx');
    }
}

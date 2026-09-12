<?php

namespace App\Http\Controllers;

use App\Enums\EmployeeStatus;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Assignment;
use App\Models\Employee;
use App\Support\Masks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $employees = Employee::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        $employees->through(fn (Employee $employee) => [
            'id' => $employee->id,
            'nama' => $employee->nama,
            'nik' => $canManage ? $employee->nik : Masks::partial($employee->nik),
            // nik and no_rekening use identical masking per spec §3.2 (decision E3)
            'no_rekening' => $canManage ? $employee->no_rekening : Masks::partial($employee->no_rekening),
            'status' => $employee->status->value,
        ]);

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $canManage,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Employees/Create');
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Employee::create([
            ...$request->validated(),
            'status' => EmployeeStatus::Aktif,
        ]);

        return redirect()->route('employees.index');
    }

    public function show(Request $request, Employee $employee): Response
    {
        $canManage = $request->user()->hasAnyRole(['admin', 'staff_input']);

        $assignments = $employee->assignments()->with('jobPeriod.job')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();

        return Inertia::render('Employees/Show', [
            'employee' => [
                'id' => $employee->id,
                'nama' => $employee->nama,
                'nik' => $canManage ? $employee->nik : Masks::partial($employee->nik),
                'alamat' => $employee->alamat,
                'no_rekening' => $canManage ? $employee->no_rekening : Masks::partial($employee->no_rekening),
                'nama_bank' => $employee->nama_bank,
                'status' => $employee->status->value,
            ],
            'assignments' => $assignments->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'job_nama_pekerjaan' => $assignment->jobPeriod->job->nama_pekerjaan,
                'no_dokumen' => $assignment->jobPeriod->no_dokumen,
                'tanggal_mulai' => $assignment->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $assignment->tanggal_selesai?->format('Y-m-d'),
                'status' => $assignment->status->value,
            ])->values(),
        ]);
    }

    public function edit(Employee $employee): Response
    {
        return Inertia::render('Employees/Edit', [
            'employee' => [
                'id' => $employee->id,
                'nama' => $employee->nama,
                'nik' => $employee->nik,
                'alamat' => $employee->alamat,
                'no_rekening' => $employee->no_rekening,
                'nama_bank' => $employee->nama_bank,
                'status' => $employee->status->value,
            ],
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()->route('employees.index');
    }

    public function toggleStatus(Employee $employee): RedirectResponse
    {
        $employee->update([
            'status' => $employee->status === EmployeeStatus::Aktif
                ? EmployeeStatus::NonAktif
                : EmployeeStatus::Aktif,
        ]);

        return redirect()->route('employees.index');
    }
}

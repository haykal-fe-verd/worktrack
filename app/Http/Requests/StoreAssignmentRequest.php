<?php

namespace App\Http\Requests;

use App\Enums\EmployeeStatus;
use App\Models\Assignment;
use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `role:admin|staff_input` route
     * middleware — this request only validates the payload shape.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'tarif_jual' => ['nullable', 'numeric', 'min:0'],
            'tarif_bayar' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $employeeId = $this->input('employee_id');

            if (! $employeeId) {
                return;
            }

            $employee = Employee::find($employeeId);

            if ($employee && $employee->status !== EmployeeStatus::Aktif) {
                $validator->errors()->add('employee_id', 'Karyawan harus berstatus aktif.');
            }

            $jobPeriod = $this->route('jobPeriod');

            if ($jobPeriod && Assignment::where('employee_id', $employeeId)
                ->where('job_period_id', $jobPeriod->id)
                ->where('is_current', true)
                ->exists()) {
                $validator->errors()->add('employee_id', 'Karyawan ini sudah punya penugasan aktif pada periode ini.');
            }
        });
    }
}

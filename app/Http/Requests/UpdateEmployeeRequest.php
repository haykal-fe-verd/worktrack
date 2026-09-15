<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['required', 'string', 'regex:/^\d{16}$/'],
            'alamat' => ['required', 'string'],
            'no_rekening' => ['required', 'string', 'regex:/^\d+$/'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $nik = $this->input('nik');

            if (! $nik) {
                return;
            }

            $exists = Employee::where('nik_hash', Employee::hashNik($nik))
                ->where('id', '!=', $this->route('employee')->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('nik', 'NIK sudah terdaftar.');
            }
        });
    }
}

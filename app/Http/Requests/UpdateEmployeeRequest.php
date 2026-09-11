<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'nik' => [
                'required',
                'string',
                'regex:/^\d{16}$/',
                Rule::unique('employees', 'nik')->ignore($this->route('employee')),
            ],
            'alamat' => ['required', 'string'],
            'no_rekening' => ['required', 'string', 'regex:/^\d+$/'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
        ];
    }
}

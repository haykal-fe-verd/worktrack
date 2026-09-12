<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EndAssignmentRequest extends FormRequest
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
            'tanggal_selesai' => [
                'required',
                'date',
                'after_or_equal:'.$this->route('assignment')->tanggal_mulai->format('Y-m-d'),
            ],
        ];
    }
}

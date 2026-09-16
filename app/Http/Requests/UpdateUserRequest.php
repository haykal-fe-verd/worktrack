<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * Authorization is enforced by the `role:admin` route middleware —
     * this request only validates the payload shape.
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
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_column(Role::cases(), 'value'))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $targetUser = $this->route('user');
            $isSelf = $targetUser && $this->user()->is($targetUser);
            $keepsAdmin = $this->input('role') === Role::Admin->value;

            if ($isSelf && ! $keepsAdmin && User::role(Role::Admin->value)->count() <= 1) {
                $validator->errors()->add('role', 'Tidak bisa menghapus role admin dari akun sendiri karena ini satu-satunya admin.');
            }
        });
    }
}

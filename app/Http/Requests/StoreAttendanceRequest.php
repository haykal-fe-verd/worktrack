<?php

namespace App\Http\Requests;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreAttendanceRequest extends FormRequest
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
            'assignment_id' => ['required', 'integer', 'exists:assignments,id'],
            'tanggal' => ['required', 'date'],
            'status' => ['required', 'string', 'in:hadir,tidak_hadir,izin'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $assignmentId = $this->input('assignment_id');
            $tanggal = $this->input('tanggal');

            if (! $assignmentId || ! $tanggal) {
                return;
            }

            $assignment = Assignment::find($assignmentId);

            if (! $assignment) {
                return;
            }

            if (! in_array($assignment->status, [AssignmentStatus::Aktif, AssignmentStatus::Diperbarui], true)) {
                $validator->errors()->add('assignment_id', 'Penugasan ini tidak bisa diisi absensinya.');

                return;
            }

            if ($assignment->tanggal_mulai->isFuture()) {
                $validator->errors()->add('assignment_id', 'Penugasan ini belum dimulai.');

                return;
            }

            $tanggalDate = Carbon::parse($tanggal)->startOfDay();
            $effectiveEnd = $assignment->tanggal_selesai ?? now()->startOfDay();

            if ($tanggalDate->lt($assignment->tanggal_mulai) || $tanggalDate->gt($effectiveEnd) || $tanggalDate->isFuture()) {
                $validator->errors()->add('tanggal', 'Tanggal di luar rentang penugasan yang valid.');
            }
        });
    }
}

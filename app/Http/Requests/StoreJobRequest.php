<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
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
            'nama_pekerjaan' => ['required', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'klien' => ['nullable', 'string', 'max:255'],
            'jenis_dokumen' => ['required', 'string', 'in:PR,PO,DO,WO'],
            'no_dokumen' => ['required', 'string', 'max:50', 'unique:job_periods,no_dokumen'],
            'kode_po' => ['nullable', 'string', 'max:50'],
            'nilai_po' => ['required', 'numeric', 'min:0'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'jumlah_tk_rencana' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}

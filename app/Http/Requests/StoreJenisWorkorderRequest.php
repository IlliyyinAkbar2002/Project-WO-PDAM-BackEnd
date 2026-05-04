<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation untuk Create master Jenis Workorder.
 *
 * Revisi Mei 2026: Skema form dinamis (`form_workorder.*` + `detail_form.*`)
 * sudah di-drop — digantikan Class Table Inheritance via `kategori_form` yang
 * menunjuk ke salah satu tabel `wo_meter` / `wo_jaringan` / `wo_infrastruktur`.
 */
class StoreJenisWorkorderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama'          => 'required|string|max:255|unique:m_jenis_workorder,nama',
            'kategori_form' => [
                'required',
                'string',
                Rule::in(['meter', 'jaringan', 'infrastruktur']),
            ],
        ];
    }

    public function messages()
    {
        return [
            'nama.required'          => 'Nama jenis workorder wajib diisi.',
            'nama.unique'            => 'Nama jenis workorder sudah digunakan.',
            'kategori_form.required' => 'Kategori form wajib diisi.',
            'kategori_form.in'       => 'Kategori form harus salah satu dari: meter, jaringan, infrastruktur.',
        ];
    }
}

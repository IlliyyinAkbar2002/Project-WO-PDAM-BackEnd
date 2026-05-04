<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation untuk Update master Jenis Workorder.
 *
 * Revisi Mei 2026: Skema form dinamis (`form_workorder.*` + `detail_form.*`)
 * sudah di-drop. Sekarang Jenis WO hanya punya nama + kategori_form.
 */
class UpdateJenisWorkorderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nama' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('m_jenis_workorder', 'nama')->ignore($this->route('jenis_workorder')),
            ],
            'kategori_form' => [
                'sometimes',
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

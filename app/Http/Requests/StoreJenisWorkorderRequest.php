<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJenisWorkorderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'nama' => 'required|string|max:255|unique:m_jenis_workorder,nama',
            'kpi_id' => 'required|integer|exists:master_kpi,id',
            'detail_form' => 'required|array|min:1',
            'detail_form.*.id' => 'required|integer',
            'detail_form.*.nama_field' => 'required|string|max:255',
            'detail_form.*.tipe_field' => 'required|string|in:text,dropdown,image,date',
            'detail_form.*.tipe_data' => 'nullable|string|in:string,integer,float',
            'detail_form.*.unit_satuan' => 'nullable|string|max:50',
            'detail_form.*.sifat' => 'required|string|in:mandatory,opsional',
            'detail_form.*.min' => 'nullable|integer',
            'detail_form.*.max' => 'nullable|integer',
            'detail_form.*.parent' => 'required|integer',
            'detail_form.*.keterangan' => 'nullable|string|max:255',
            'detail_form.*.hint_text' => 'required|string|max:50',
            'detail_form.*.order' => 'required|integer|min:1',
            'detail_form.*.option_form' => 'nullable|array',
            'detail_form.*.option_form.*.id' => 'required|integer',
            'detail_form.*.option_form.*.nama_opsi' => 'required|string|max:255',
            'detail_form.*.option_form.*.parent' => 'required|integer',
            'detail_form.*.option_form.*.order' => 'required|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            'nama.required' => 'Nama jenis workorder wajib diisi.',
            'nama.unique' => 'Nama jenis workorder sudah digunakan.',
            'kpi_id.required' => 'KPI ID wajib diisi.',
            'kpi_id.exists' => 'KPI ID tidak valid.',
            'detail_form.required' => 'Detail form wajib diisi.',
            'detail_form.*.nama_field.required' => 'Nama field wajib diisi.',
            'detail_form.*.option_form.*.nama_opsi.required' => 'Nama opsi wajib diisi.',
        ];
    }
}

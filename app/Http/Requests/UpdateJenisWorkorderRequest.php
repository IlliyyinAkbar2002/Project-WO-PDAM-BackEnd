<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJenisWorkorderRequest extends FormRequest
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
            'nama' => 'required|string|max:255|unique:m_jenis_workorder,nama,' . $this->route('jenis_workorder'),
            'form_workorder' => 'required|array|min:1',
            'form_workorder.*.id' => 'required|integer',
            'form_workorder.*.kpi_id' => 'required|integer',
            'form_workorder.*.nama_field' => 'required|string|max:255',
            'form_workorder.*.tipe_field' => 'required|string|in:text,dropdown,image,date',
            'form_workorder.*.tipe_data' => 'nullable|string|in:string,integer,float',
            'form_workorder.*.unit_satuan' => 'nullable|string|max:50',
            'form_workorder.*.sifat' => 'required|string|in:mandatory,opsional',
            'form_workorder.*.min' => 'nullable|integer',
            'form_workorder.*.max' => 'nullable|integer',
            'form_workorder.*.parent' => 'required|integer',
            'form_workorder.*.keterangan' => 'nullable|string|max:255',
            'form_workorder.*.hint_text' => 'required|string|max:50',
            'form_workorder.*.order' => 'required|integer|min:1',
            'form_workorder.*.detail_form' => 'nullable|array',
            'form_workorder.*.detail_form.*.id' => 'required|integer',
            'form_workorder.*.detail_form.*.nama_opsi' => 'required|string|max:255',
            'form_workorder.*.detail_form.*.parent' => 'required|integer',
            'form_workorder.*.detail_form.*.order' => 'required|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            'nama.required' => 'Nama jenis workorder wajib diisi.',
            'nama.unique' => 'Nama jenis workorder sudah digunakan.',
            'form_workorder.required' => 'Form workorder wajib diisi.',
            'form_workorder.*.kpi_id.required' => 'KPI ID wajib dipilih.',
            'form_workorder.*.nama_field.required' => 'Nama field wajib diisi.',
            'form_workorder.*.detail_form.*.nama_opsi.required' => 'Nama opsi wajib diisi.',
        ];
    }
}

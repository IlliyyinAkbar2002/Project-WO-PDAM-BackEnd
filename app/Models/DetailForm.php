<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailForm extends Model
{
    use HasFactory;
    protected $table = 'detail_form';
    protected $guarded = [];

    // public function form()
    // {
    //     return $this->belongsTo(Form::class, 'form_id');
    // }

    public function jenisWorkorder()
    {
        return $this->belongsTo(JenisWorkorder::class, 'jenis_workorder_id');
    }

    public function optionForm()
    {
        return $this->hasMany(OptionForm::class, 'detail_form_id');
    }
}

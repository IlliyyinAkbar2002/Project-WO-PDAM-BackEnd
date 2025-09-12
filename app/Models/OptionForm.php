<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OptionForm extends Model
{
    use HasFactory;
    protected $table = 'option_form';
    protected $guarded = [];

    public function detailForm()
    {
        return $this->belongsTo(DetailForm::class, 'detail_form_id');
    }
}

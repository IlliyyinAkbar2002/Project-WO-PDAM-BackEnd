<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterAction extends Model
{
    use HasFactory;
    protected $table = 'm_action';
    protected $guarded = [];
    protected $fillable = ['nama','latitude','longitude','radius_meter'];
    protected $casts = ['latitude'=>'float','longitude'=>'float','radius_meter'=>'int'];

    public function workorderAction()
    {
        return $this->hasMany(WorkorderAction::class, 'action_id');
    }
}

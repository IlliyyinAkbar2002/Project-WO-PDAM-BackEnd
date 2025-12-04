<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterLocation extends Model
{
    use HasFactory;
    protected $table = 'm_location';
    protected $guarded = [];
    protected $fillable = ['nama','latitude','longitude','radius_meter'];
    protected $casts = ['latitude'=>'float','longitude'=>'float','radius_meter'=>'int'];

    public function workorders()
    {
        return $this->hasMany(Workorder::class, 'location_id');
    }
}

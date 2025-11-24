<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLocations extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','latitude','longitude','accuracy'];
    protected $casts = ['latitude'=>'float','longitude'=>'float','accuracy'=>'float'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

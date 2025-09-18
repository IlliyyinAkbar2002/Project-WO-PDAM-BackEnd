<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;
    protected $table = 'm_role';
    protected $primaryKey = 'id';
    public $timestamps = false;

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}

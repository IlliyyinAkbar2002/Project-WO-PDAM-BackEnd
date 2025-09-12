<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterAction extends Model
{
    use HasFactory;
    protected $table = 'm_action';
    protected $guarded = [];

    public function workorderAction()
    {
        return $this->hasMany(WorkorderAction::class, 'action_id');
    }
}

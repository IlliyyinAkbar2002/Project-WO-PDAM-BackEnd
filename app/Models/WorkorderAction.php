<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkorderAction extends Model
{
    use HasFactory;
    protected $table = 'workorder_action';
    protected $guarded = [];

    public function workorder()
    {
        return $this->belongsTo(Workorder::class, 'workorder_id');
    }

    public function action()
    {
        return $this->belongsTo(MasterAction::class, 'action_id');
    }
}

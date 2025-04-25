<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Employe;

class TaskPic extends Model
{
    use HasFactory;
    protected $table = 'project_task_pics';
    protected $fillable = [
        'project_id',
        'employe_id',
        'task_id',
    ];

    public function employee()
    {
        return $this->belongsTo(Employe::class, 'employe_id', 'employe_id');
    }
}

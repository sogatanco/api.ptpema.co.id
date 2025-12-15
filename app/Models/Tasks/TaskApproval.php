<?php

namespace App\Models\Tasks;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskApproval extends Model
{
    use HasFactory;
    protected $table = "project_task_approval";
    protected $primaryKey = 'approval_id';
    protected $fillable = [
        'task_id',
        'employe_id',
        'status',
        'start_date',
        'file',
        'notes',
        'status_by',
        'end_date',
        'progress'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;
    protected $table = 'project_notifications';
    protected $fillable = [
        'from_employe',
        'to_employe',
        'project_id',
        'task_id',
        'title',
        'desc',
        'category',
        'url',
        'read_status'
    ];
}

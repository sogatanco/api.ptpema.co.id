<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectStage extends Model
{
    use HasFactory;
    protected $table = 'project_stages';
    protected  $fillable = [
        'project_id',
        'phase',
        'division',
        'title', 
        'start_date',
        'end_date',
        'desc',
        'schema',
        'partner',
        'status'
    ];
}
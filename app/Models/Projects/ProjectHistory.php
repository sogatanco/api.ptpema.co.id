<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectHistory extends Model
{
    protected $table = "project_histories";
    protected $pimaryKey = "history_id";
    protected $keyType = "int";
    public $timestamps = true;
    protected $guarded = [];

    protected $fillable = [
        'project_id',
        'employe_id',
        'history_desc',
        'active',
        'comment',
        'file',
        'status',
        'review_by'
    ];

    public function project(): BelongsToMany
    {
        return $this->belongsTo(ProjectHistory::class, 'project_id', 'project_id');
    }

    public function employe(): BelongsToMany
    {
        return $this->belongsTo(ProjectHistory::class, 'employe_id', 'employe_id');
    }
}

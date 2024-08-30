<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Http\Models\Projects\ProjectHistory;
use App\Http\Models\Projects\ProjectTask;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Project extends Model
{
    protected $table = "projects";
    protected $pimaryKey = "project_id";
    protected $keyType = "int";
    public $timestamps = true;
    protected $guarded = [];

    // protected $fillable = array('*');
    protected $fillable = [
        "id",
        "project_number",
        "division",
        "project_name",
        "goals",
        "estimated_income",
        "estimated_cost",
        "base_id",
        "level_id",
        "business_id",
        "created_by",
        "category"
    ];


    public function employe(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'created_by', 'employe_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'division', 'organization_id');
    }

    public function project_history(): HasMany
    {
        return $this->hasMany(ProjectHistory::class, 'project_id', 'project_id');
    }    

    protected $with = ['project_task'];
    public function project_task(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_id', 'project_id');
    }
}

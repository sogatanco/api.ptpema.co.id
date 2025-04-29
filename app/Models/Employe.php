<?php

namespace App\Models;

use App\Models\Daily\DailyComment;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employe extends Model
{
    use HasFactory;
    protected $table = 'employees';
    protected $primaryKey = 'employe_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $casts = [
    'employe_id' => 'string',
];
    protected $fillable = [
        'employe_id',
        'public_key',
        'user_id',
        'position_id',
        'first_name',
        'last_name',
        'gender',
        'religion',
        'birthday',
        'birthday_place',
        'marital_status',
        'img',
    ];


   public function user(): BelongsTo
   {
       return $this->belongsTo(Employe::class, 'user_id', 'id');
   }

   public function project(): HasMany
   {
       return $this->hasMany(Project::class, 'created_by', 'employe_id');
   }

   public function project_history(): HasMany
   {
       return $this->hasMany(ProjectHistory::class, 'employe_id', 'employe_id');
   }

   public static function employeId()
   {
        $user = auth()->user();
        $employe = Employe::where('user_id', $user->id)->first('employe_id');

        return $employe->employe_id;
   }

   public static function getEmployeDivision($employe_id){
       return Employe::where('employees.employe_id', '=' , $employe_id)
                    ->leftJoin('positions', 'employees.position_id', '=', 'positions.position_id')
                    ->leftJoin('organizations', 'organizations.organization_id', '=', 'positions.organization_id')
                    ->select('positions.position_id', 'positions.position_name', 'organizations.organization_id', 'organizations.organization_name', 'organizations.board_id')
                    ->first();
    }

    public function comments()
    {
        return $this->hasMany(DailyComment::class, 'employe_id', 'employe_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}

<?php

namespace App\Models;

use App\Models\Daily\DailyComment;
use App\Models\Projects\Project;
use App\Models\Hr\Position as HrPosition;
use App\Models\Projects\ProjectHistory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Employe extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    // View employees_hr (db/hr_sync/35): semua data karyawan dari HR; user_id lewat users.employe_id; public_key dari HR employee_public_keys. Hanya-baca.
    protected $table = 'employees_hr';
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
        // users.employe_id menaut akun apam ke karyawan HR; cadangan lewat view employees_hr
        $id = DB::table('users')->where('id', $user->id)->value('employe_id')
            ?: Employe::where('user_id', $user->id)->value('employe_id');

        return $id;
   }

   public static function getEmployeDivision($employe_id){
       // Jabatan, divisi, dan board dari HR (struktur_lengkap_oke)
       $row = Structure::where('employe_id', $employe_id)
                    ->select('position_id', 'position_name', 'organization_id', 'organization_name', 'board_id')
                    ->first();
       if ($row) {
           return $row;
       }

       // Karyawan HR tanpa baris di view struktur (mis. nonaktif): jabatan dan divisi langsung dari tabel HR
       $hr = config('database.connections.hr.database');
       return DB::table('employees_hr as e')
                    ->join($hr . '.positions as p', 'p.id', '=', 'e.position_id')
                    ->join($hr . '.divisions as d', 'd.id', '=', 'p.division_id')
                    ->where('e.employe_id', $employe_id)
                    ->select('p.id as position_id', 'p.position_name', 'd.id as organization_id', 'd.division_name as organization_name', 'd.board_id')
                    ->first();
    }

    public function comments()
    {
        return $this->hasMany(DailyComment::class, 'employe_id', 'employe_id');
    }

    public function position()
    {
        return $this->belongsTo(HrPosition::class, 'position_id', 'id');
    }
}

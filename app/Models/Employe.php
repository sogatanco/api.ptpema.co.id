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

class Employe extends Model
{
    use HasFactory;
    protected $connection = 'mysql';
    // View employees_hr (db/hr_sync/21_employees_hr_view.sql): nama, jabatan (id HR), status aktif dari HR; public_key/user_id dari tabel employees.
    // Penulisan lewat App\Models\EmployeBase.
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
        $employe = EmployeBase::where('user_id', $user->id)->first('employe_id');
        if (!$employe) {
            // karyawan baru dari HR yang belum punya baris di tabel employees: kaitan lewat email akun (employees_hr)
            $employe = Employe::where('user_id', $user->id)->first('employe_id');
        }

        return $employe->employe_id;
   }

   public static function getEmployeDivision($employe_id){
       // Jabatan, divisi, dan board dari HR (struktur_lengkap_oke)
       $row = Structure::where('employe_id', $employe_id)
                    ->select('position_id', 'position_name', 'organization_id', 'organization_name', 'board_id')
                    ->first();
       if ($row) {
           return $row;
       }

       // Karyawan yang tidak ada di HR (nonaktif/dihapus): data lama di tabel employees
       return EmployeBase::where('employees.employe_id', '=' , $employe_id)
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
        return $this->belongsTo(HrPosition::class, 'position_id', 'id');
    }
}

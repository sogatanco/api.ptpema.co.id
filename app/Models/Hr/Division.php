<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

// Divisi menurut HR (id = organization_id apam, nama dan board sama). Dipakai modul project/task sebagai pengganti tabel apam `organizations`.
class Division extends Model
{
    protected $connection = 'hr';
    protected $table = 'divisions';

    // Daftar divisi berbentuk baris `organizations` apam (organization_id, organization_code, organization_name, board_id)
    public function scopeAsOrganizations($query)
    {
        return $query->selectRaw('divisions.id as organization_id, divisions.division_code as organization_code, divisions.division_name as organization_name, divisions.board_id as board_id');
    }

    // Ekspresi tabel untuk join dari koneksi apam: join(Division::organizationsTable(), 'organizations.organization_id', '=', ...)
    public static function organizationsTable()
    {
        $db = config('database.connections.hr.database');

        return DB::raw("(select id as organization_id, division_code as organization_code, division_name as organization_name, board_id as board_id from `{$db}`.`divisions`) as organizations");
    }
}

<?php

namespace App\Models\Kontrak;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'w12';

    protected $table = 'contracs';

    protected $fillable = [
        'no_contrac',
        'judul',
        'partner',
        'start',
        'end',
        'pic',
        'file_url',
        'created_by',
    ];

    protected $casts = [
        'start' => 'date',
        'end' => 'date',
        'deleted_at' => 'datetime',
    ];

    public function history()
    {
        return $this->hasMany(ContractHistory::class, 'contract_id', 'id');
    }
}

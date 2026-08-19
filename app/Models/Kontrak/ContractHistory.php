<?php

namespace App\Models\Kontrak;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContractHistory extends Model
{
    use HasFactory;

    protected $table = 'contract_history';
    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'action',
        'action_by',
        'action_time',
    ];

    protected $casts = [
        'action_time' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'id');
    }
}

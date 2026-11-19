<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Model;

class ProsesTiket extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Sppd\Scopes\SppdAwalScope());
    }

    protected $connection = 'mysql4';
    protected $table = 'proses_tiket';
}

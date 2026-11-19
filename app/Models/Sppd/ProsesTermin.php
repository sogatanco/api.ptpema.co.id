<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Model;

/** Bukti transfer uang muka per termin (db/2026-11-18/proses_termin.sql) */
class ProsesTermin extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new \App\Models\Sppd\Scopes\SppdAwalScope());
    }

    protected $connection = 'mysql4';
    protected $table = 'proses_termin';
}

<?php

namespace App\Models\Sppd\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tiket dan termin milik SPPD awal (id_ekstend = 0). Tiket/termin extend (id_ekstend <> 0) diambil lewat withoutGlobalScopes().
 * Bila kolom id_ekstend belum ada (db/2026-11-18/ekstend_proses.sql belum dijalankan), scope tidak berbuat apa-apa.
 */
class SppdAwalScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tabel = $model->getTable();
        $ada = Cache::remember('kolom_id_ekstend_' . $tabel, 300, fn () => Schema::connection($model->getConnectionName())->hasColumn($tabel, 'id_ekstend'));
        if ($ada) {
            $builder->where($tabel . '.id_ekstend', 0);
        }
    }
}

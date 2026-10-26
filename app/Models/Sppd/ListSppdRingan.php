<?php

namespace App\Models\Sppd;

use Illuminate\Database\Eloquent\Model;

// list_sppd without perkiraan_total, uang_muka, rill_biaya, rill_tiket (see PengajuanController::withBiaya)
class ListSppdRingan extends Model
{
    protected $connection='mysql4';
    protected $table='list_sppd_ringan';
}

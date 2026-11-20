<?php

namespace App\Models\Meeting;

use Illuminate\Database\Eloquent\Model;

/** Blokir ruang rapat oleh Sekretaris Direksi (db/2026-11-20/blokir_ruang_rapat.sql); tidak ditampilkan di /jadwal-rapat */
class BlokirRuang extends Model
{
    protected $table = 'meeting_blokir';

    protected $fillable = ['room', 'start_time', 'end_time', 'alasan', 'created_by', 'canceled_at'];
}

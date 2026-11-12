<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Model;

/** Draft surat: belum bernomor dan belum masuk alur paraf; isi form disimpan apa adanya di payload (JSON). */
class SuratDraft extends Model
{
    protected $connection = 'adm';
    protected $table = 'surat_draft';
    protected $guarded = [];
}

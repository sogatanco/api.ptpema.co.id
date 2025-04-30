<?php

namespace App\Models\Daily;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Employe;

class DailyComment extends Model
{
    use HasFactory;

    protected $table = 'daily_comments';

    protected $fillable = [
        'daily_id',
        'employe_id',
        'reply_id',
        'comment',
        'attachment_file',
    ];

    // Pegawai yang membuat komentar
    public function employee()
    {
        return $this->belongsTo(Employe::class, 'employe_id', 'employe_id');
    }

    // Komentar yang dibalas
    public function reply()
    {
        return $this->belongsTo(DailyComment::class, 'reply_id');
    }

    public function replies()
    {
        return $this->hasMany(DailyComment::class, 'reply_id');
    }

}

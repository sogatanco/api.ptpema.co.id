<?php

namespace App\Models\Daily;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Employe;

class DailyLog extends Model
{
    use HasFactory;
    protected $table = 'daily_logs';
    protected $guarded = [];

    public function employee()
    {
        return $this->belongsTo(Employe::class, 'employe_id', 'employe_id');
    }
}

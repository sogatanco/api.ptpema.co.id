<?php

namespace App\Models\Meeting;

use App\Models\Meeting\Zoom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingRoom extends Model
{
    use HasFactory;

    protected $table = 'meetings';

    protected $fillable = [
        'topic',
        'participants',
        'start_time',
        'end_time',
        'zoom_required',
        'consumption_required',
        'consumption_detail',
        'room',
        'zoom_id',
        'created_by',
        'canceled_at',
    ];

    protected $casts = [
        'participants' => 'integer',
        'zoom_required' => 'boolean',
        'consumption_required' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function zoom()
    {
        return $this->belongsTo(Zoom::class, 'zoom_id');
    }
}

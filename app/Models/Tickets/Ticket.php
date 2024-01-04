<?php

namespace App\Models\TIckets;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;
    protected $table = 'tickets';
    protected $fillable = [
        'ticket_number',
        'requester',
        'subject',
        'title',
        'desc',
        'pic',
        'priority'
    ];
}

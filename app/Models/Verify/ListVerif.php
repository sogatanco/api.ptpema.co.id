<?php

namespace App\Models\Verify;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ListVerif extends Model
{
    protected $connection='esign';
    protected $table = 'list_verif';
}

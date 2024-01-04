<?php

namespace App\Models\Comment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;
    protected $table = 'comments';
    public $timestamps = true;
    public $incrementing = true;
    protected $fillable = [
        'employe_id',
        'task_id',
        'comment'
    ];
}

<?php

namespace App\Models\Adm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovedDocument extends Model
{
   protected $connection='adm';
   protected $table= 'approved_document';

}

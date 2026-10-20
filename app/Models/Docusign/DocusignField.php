<?php

namespace App\Models\Docusign;

use Illuminate\Database\Eloquent\Model;

class DocusignField extends Model
{
    protected $connection = 'adm';

    protected $table = 'docusign_fields';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'w' => 'float',
        'h' => 'float',
    ];
}

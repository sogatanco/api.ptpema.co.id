<?php

namespace App\Models\Docusign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocusignSigner extends Model
{
    protected $connection = 'adm';

    protected $table = 'docusign_signers';

    protected $guarded = [];

    protected $hidden = ['token'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocusignDocument::class, 'document_id');
    }
}

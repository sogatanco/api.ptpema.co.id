<?php

namespace App\Models\Docusign;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocusignDocument extends Model
{
    protected $connection = 'adm';

    protected $table = 'docusign_documents';

    protected $guarded = [];

    public function signers(): HasMany
    {
        return $this->hasMany(DocusignSigner::class, 'document_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DocusignField::class, 'document_id');
    }
}

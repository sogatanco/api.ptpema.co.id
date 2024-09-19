<?php

namespace App\Http\Controllers\Verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\PostResource;
use App\Models\Verify\ListVerif;

class ScanVerif extends Controller
{
    function getDetail($id_doc, Request $request )
    {
        $data = ListVerif::where('id_doc', $id_doc)->where('type', $request->type)->first();
        return new PostResource(true, 'success', $data);
    }
}

<?php

namespace App\Http\Controllers\Meeting;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Meeting\Zoom;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function bookZoom(Request $request)
    {
        $zoom = new Zoom();
        $zoom->topic = $request->input('topic');
        $zoom->link = $request->input('link');
        $zoom->meeting_id = $request->input('meeting_id');
        $zoom->password = $request->input('password');
        $zoom->start_time = $request->input('start_time');
        $zoom->end_time = $request->input('end_time');
        $zoom->created_by = Employe::employeId();

        if ($zoom->save()) {
            return new PostResource(true, 'Zoom booked successfully', $zoom);
        }

        return new PostResource(false, 'Failed to book zoom', []);
    }

    public function listZoom()
    {
        $data = Zoom::whereNull('canceled_at')
            ->orderBy('start_time', 'asc')
            ->get();

        foreach ($data as $item) {
            $item->created_by_name = Employe::where('employe_id', $item->created_by)->value('first_name');
        }

        return new PostResource(true, 'success', $data);
    }

    public function cancelZoom($id)
    {
        $zoom = Zoom::find($id);

        if (!$zoom) {
            return new PostResource(false, 'Zoom not found', []);
        }

        if (!is_null($zoom->canceled_at)) {
            return new PostResource(false, 'Zoom already canceled', []);
        }

        $zoom->canceled_at = now();
        if ($zoom->save()) {
            return new PostResource(true, 'Zoom canceled successfully', $zoom);
        }

        return new PostResource(false, 'Failed to cancel zoom', []);
    }
}

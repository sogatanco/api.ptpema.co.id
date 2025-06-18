<?php

namespace App\Modules\Daily\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'activity_name' => $this->activity_name,
            'category' => $this->category,
            'progress' => $this->progress,
            'start_date' => [
                'date' => $this->start_date ? Carbon::parse($this->start_date)->format('Y-m-d') : null,
                'time' => $this->start_date ? Carbon::parse($this->start_date)->format('H:i') : null,
            ],
            'end_date' => [
                'date' => $this->end_date ? Carbon::parse($this->end_date)->format('Y-m-d') : null,
                'time' => $this->end_date ? Carbon::parse($this->end_date)->format('H:i') : null,
            ],
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

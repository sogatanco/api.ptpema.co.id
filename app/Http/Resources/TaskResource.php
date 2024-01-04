<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
       return [
            "project_id" => $this->project_id,
            "project_name" => $this->project_name,
            "task_parent" => $this->task_parent,
            "task_title" => $this->task_title,
            "task_desc" => $this->task_desc,
            "employe_id" => $this->employe_id,
            "task_progress" => $this->task_progress,
            "status" => $this->status
       ];
    }
}

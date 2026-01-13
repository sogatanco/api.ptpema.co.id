<?php

namespace App\Modules\Daily\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyByCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Collection $dailies */
        $dailies = collect($this['dailies']); // ambil array of daily dari hasil repo

        $minStart = $dailies->min('start_date');
        $maxEnd = $dailies->max('end_date');

        $timeline = $this->formatTimeline($minStart, $maxEnd);
        $totalProgress = round($dailies->avg('progress'));

        return [
            'category' => $this['category'],
            'task_progress' => $totalProgress,
            'date_range' => $timeline,
            'daily' => $dailies->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'task_id' => $item['task_id'],
                    'activity_name' => $item['activity_name'],
                    'category' => $item['category'],
                    'is_priority' => $item['is_priority'],
                    'progress' => $item['progress'],
                    'start_date' => [
                        'date' => Carbon::parse($item['start_date'])->format('Y-m-d'),
                        'time' => Carbon::parse($item['start_date'])->format('H:i'),
                    ],
                    'end_date' => [
                        'date' => Carbon::parse($item['end_date'])->format('Y-m-d'),
                        'time' => Carbon::parse($item['end_date'])->format('H:i'),
                    ],
                    'date_range' => $this->formatTimeline($item['start_date'], $item['end_date']),
                    'status' => $item['status'],
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at'],
                ];
            }),
        ];
    }

    protected function formatTimeline($start, $end): string
    {
        $start = Carbon::parse($start);
        $end = Carbon::parse($end);

        if ($start->equalTo($end)) {
            return $start->translatedFormat('d F Y'); // ex: 18 Mei 2025
        }

        if ($start->year === $end->year) {
            if ($start->month === $end->month) {
                return $start->format('d') . ' - ' . $end->translatedFormat('d F Y');
            }

            return $start->translatedFormat('d F') . ' - ' . $end->translatedFormat('d F Y');
        }

        return $start->translatedFormat('d F Y') . ' - ' . $end->translatedFormat('d F Y');
    }
}

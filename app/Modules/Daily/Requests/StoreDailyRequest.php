<?php

namespace App\Modules\Daily\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyRequest extends FormRequest
{
    public function prepareForValidation()
    {
        $dailies = collect($this->input('dailies', []))->map(function ($item) {
            return [
                'task_id' => $item['taskId'] ?? null,
                'activity_name' => $item['activityName'] ?? null,
                'category' => $item['category'] ?? null,
                'start_date' => $item['startDate'] ?? null,
                'end_date' => $item['endDate'] ?? null,
            ];
        });

        $this->merge([
            'dailies' => $dailies->toArray()
        ]);
    }

    public function rules(): array
    {
        return [
            'dailies' => ['required', 'array', 'min:1'],
            'dailies.*.task_id' => ['nullable', 'integer'],
            'dailies.*.activity_name' => ['required', 'string'],
            'dailies.*.category' => ['required', 'string'],
            'dailies.*.start_date' => ['required', 'date'],
            'dailies.*.end_date' => ['required', 'date'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

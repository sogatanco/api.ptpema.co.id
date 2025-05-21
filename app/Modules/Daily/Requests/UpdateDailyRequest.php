<?php

namespace App\Modules\Daily\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'exists:daily,id'],
            'activity_name' => ['required'],
            'category' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

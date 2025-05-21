<?php

namespace App\Modules\Daily\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteDailyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'dailies' => ['required', 'array', 'min:1'],
            'dailies.*' => ['required', 'exists:daily,id'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

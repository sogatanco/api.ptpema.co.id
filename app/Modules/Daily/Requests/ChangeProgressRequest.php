<?php

namespace App\Modules\Daily\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeProgressRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'dailies' => ['required', 'array', 'min:1'],
            'dailies.*.id' => ['required', 'exists:daily,id'],
            'dailies.*.progress' => ['required', 'integer', 'min:0', 'max:100'],
            'dailies.*.change_to_review' => ['nullable', 'boolean'], // hanya relevan jika progress == 100
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

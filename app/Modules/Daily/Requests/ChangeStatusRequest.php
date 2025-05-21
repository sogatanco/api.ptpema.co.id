<?php

namespace App\Modules\Daily\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
     public function rules(): array
    {
        return [
            'dailies' => ['required', 'array', 'min:1'],
            'dailies.*.id' => ['required', 'exists:daily,id'],
            'dailies.*.progress' => ['required', 'integer', 'between:0,100'],
            'dailies.*.status' => [
                'required',
                Rule::in(['in_progress', 'review', 'approved', 'revised', 'cancelled']),
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $progress = data_get($this->input(), "dailies.{$index}.progress");

                    if ($value === 'review' && $progress < 100) {
                        $fail("Status 'review' hanya bisa digunakan jika progress adalah 100%.");
                    }
                },
            ],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}

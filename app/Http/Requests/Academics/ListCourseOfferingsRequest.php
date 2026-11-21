<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;

class ListCourseOfferingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission is checked in the controller
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'semester' => ['nullable', 'string', 'size:26'],
            'programme' => ['nullable', 'string', 'size:26'],
            'department' => ['nullable', 'string', 'size:26'],
            'year' => ['nullable', 'integer', 'between:1,10'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

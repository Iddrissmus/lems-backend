<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked by the policy in the controller
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'programme' => ['nullable', 'string', 'size:26'],
            'cohort' => ['nullable', 'string', 'size:26'],
            'department' => ['nullable', 'string', 'size:26'],
            'year' => ['nullable', 'integer', 'between:1,10'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

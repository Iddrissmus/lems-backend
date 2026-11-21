<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;

class SaveMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // checked in the controller
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'marks' => ['required', 'array', 'min:1', 'max:500'],
            'marks.*.enrolment_id' => ['required', 'string', 'size:26', 'distinct'],
            // 0 to 100 with at most 2 decimals; null clears a draft mark.
            'marks.*.score' => ['present', 'nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<int, array{enrolment_id: string, score: float|int|string|null}>
     */
    public function marks(): array
    {
        /** @var array<int, array{enrolment_id: string, score: float|int|string|null}> */
        return array_values($this->validated('marks'));
    }
}

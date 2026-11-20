<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCoursesRequest extends FormRequest
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
            'course_offering_ids' => ['required', 'array', 'min:1', 'max:20'],
            'course_offering_ids.*' => ['string', 'size:26', 'distinct'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function offeringIds(): array
    {
        /** @var array<int, string> */
        return array_values($this->validated('course_offering_ids'));
    }
}

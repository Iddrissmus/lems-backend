<?php

namespace App\Http\Requests\Academics;

use App\Domain\Academics\Enums\OfferingMarkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListMarkEntryOfferingsRequest extends FormRequest
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
            'semester' => ['sometimes', 'string', 'size:26'],
            'department' => ['sometimes', 'string', 'size:26'],
            'status' => ['sometimes', Rule::enum(OfferingMarkStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The class list of a course with each student's mark, as built by BuildMarksSheet.
 * Staff only: it carries numeric scores, which a student never receives.
 *
 * @property array<string, mixed> $resource
 */
class MarksSheetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

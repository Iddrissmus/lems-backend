<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's released results as built by GetMyResults: letter grade and credit hours
 * per course. Never a score, percentage or GPA.
 *
 * @property array<string, mixed> $resource
 */
class MyResultsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return ['meta' => ['note' => 'Letter grades and credit hours only. GPA is not computed by the API; use GET /grade-scale for the sample points.']];
    }
}

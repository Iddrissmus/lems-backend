<?php

namespace App\Http\Controllers\Academics;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\GradeScaleResource;
use App\Models\GradeScale;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;

#[Group('Grade scale', weight: 43)]
class GradeScaleController extends Controller
{
    #[Endpoint(title: 'The active grade scale', description: 'Any signed-in user. The letters, score bands and points in use. For the demo it is synthetic (is_demo true) and official is always false; the points exist so a screen can show a sample GPA labelled not official. The API never calculates or stores a GPA. 404 when no scale is active.')]
    public function __invoke(): GradeScaleResource
    {
        $scale = GradeScale::active()
            ?? throw new ApiException(404, 'not_found', 'No grade scale is active.');

        return new GradeScaleResource($scale);
    }
}

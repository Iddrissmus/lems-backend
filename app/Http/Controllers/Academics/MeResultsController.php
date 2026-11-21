<?php

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Actions\GetMyResults;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MyResultsResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A student's own released results. Works only on the signed-in student's own record.
 */
#[Group('My results (students)', weight: 42)]
class MeResultsController extends Controller
{
    #[Endpoint(title: 'My results', description: 'Requires results:view-own. Released courses only, grouped by semester, with letter grade and credit hours. Never a numeric mark, percentage or GPA: the frontend calculates a sample GPA from GET /grade-scale and labels it not official. semesters is empty when nothing has been released. 404 if the account has no student record.')]
    public function show(Request $request, GetMyResults $results): MyResultsResource
    {
        Gate::authorize('viewOwnResults');

        /** @var User $user */
        $user = $request->user();

        $student = $user->studentProfile
            ?? throw new ApiException(404, 'not_found', 'There is no student record for this account.');

        return new MyResultsResource($results->execute($student));
    }
}

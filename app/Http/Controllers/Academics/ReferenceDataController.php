<?php

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Actions\ListCourseOfferings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\ListCourseOfferingsRequest;
use App\Http\Resources\CohortResource;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\ProgrammeResource;
use App\Http\Resources\SemesterResource;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Programme;
use App\Models\Semester;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only lists that fill the registration screens' filters. Open to anyone who
 * works with students or registration (students:view, registration:manage,
 * registration:self, accounts:create).
 */
#[Group('Academic reference', weight: 30)]
class ReferenceDataController extends Controller
{
    #[Endpoint(title: 'List departments', description: 'Needs students:view, registration:manage, registration:self or accounts:create.')]
    public function departments(): AnonymousResourceCollection
    {
        Gate::authorize('viewReferenceData');

        return DepartmentResource::collection(Department::query()->orderBy('name')->get());
    }

    #[Endpoint(title: 'List programmes', description: 'Optional filter department (a department id). Needs the same permissions as departments.')]
    public function programmes(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewReferenceData');

        return ProgrammeResource::collection(
            Programme::query()
                ->with('department')
                ->when($request->query('department'), fn ($q, $ulid) => $q->whereHas('department', fn ($d) => $d->where('ulid', $ulid)))
                ->orderBy('name')
                ->get(),
        );
    }

    #[Endpoint(title: 'List cohorts', description: 'Optional filter programme (a programme id). Needs the same permissions as departments.')]
    public function cohorts(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewReferenceData');

        return CohortResource::collection(
            Cohort::query()
                ->with('programme')
                ->when($request->query('programme'), fn ($q, $ulid) => $q->whereHas('programme', fn ($p) => $p->where('ulid', $ulid)))
                ->orderByDesc('intake_year')
                ->get(),
        );
    }

    #[Endpoint(title: 'List semesters', description: 'Optional filter current=1 for the current semester only. Needs the same permissions as departments.')]
    public function semesters(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewReferenceData');

        return SemesterResource::collection(
            Semester::query()
                ->with('academicYear')
                ->when($request->boolean('current'), fn ($q) => $q->where('is_current', true))
                ->orderByDesc('starts_on')
                ->get(),
        );
    }

    #[Endpoint(title: 'List courses on offer', description: 'Courses offered in a semester (the current one unless semester is given), paged. Filters: programme, department (ids), year, q (code or title). Needs the same permissions as departments.')]
    public function courseOfferings(ListCourseOfferingsRequest $request, ListCourseOfferings $list): AnonymousResourceCollection
    {
        Gate::authorize('viewReferenceData');

        return CourseOfferingResource::collection($list->execute($request->validated()));
    }
}

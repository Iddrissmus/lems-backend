<?php

namespace App\Http\Controllers\Academics;

use App\Domain\Academics\Actions\CloseRegistrationWindow;
use App\Domain\Academics\Actions\CreateRegistrationWindow;
use App\Domain\Academics\Actions\ListRegistrationWindows;
use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\ListRegistrationWindowsRequest;
use App\Http\Requests\Academics\StoreRegistrationWindowRequest;
use App\Http\Resources\RegistrationWindowResource;
use App\Models\RegistrationWindow;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Registration windows', weight: 31)]
class RegistrationWindowController extends Controller
{
    #[Endpoint(title: 'List registration windows', description: 'Requires registration:manage. Filters: semester (a semester id) and status (not_yet_open, open, closed), paged.')]
    public function index(ListRegistrationWindowsRequest $request, ListRegistrationWindows $list): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RegistrationWindow::class);

        return RegistrationWindowResource::collection($list->execute($request->validated()));
    }

    #[Endpoint(title: 'Open a registration window', description: 'Requires registration:manage. Students can register for courses only while a window is open for their programme and year. audience is a list of {programme_id, year_of_study}; empty means everyone. Send an Idempotency-Key header when retrying.')]
    public function store(StoreRegistrationWindowRequest $request, CreateRegistrationWindow $create): JsonResponse
    {
        Gate::authorize('create', RegistrationWindow::class);

        /** @var User $actor */
        $actor = $request->user();

        /** @var array{semester_id: string, opens_at: string, closes_at: string, audience?: array<int, array{programme_id: string, year_of_study: int}>|null} $data */
        $data = $request->validated();

        $window = $create->execute($data, $actor);

        return (new RegistrationWindowResource($window->load('semester.academicYear')))
            ->response()
            ->setStatusCode(201);
    }

    #[Endpoint(title: 'Close a registration window early', description: 'Requires registration:manage. Closing a window that is already closed does nothing and still answers 200.')]
    public function close(Request $request, RegistrationWindow $window, CloseRegistrationWindow $close): RegistrationWindowResource
    {
        Gate::authorize('close', $window);

        /** @var User $actor */
        $actor = $request->user();

        return new RegistrationWindowResource($close->execute($window, $actor)->load('semester.academicYear'));
    }
}

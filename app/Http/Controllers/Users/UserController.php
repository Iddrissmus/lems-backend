<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\ListUsers;
use App\Domain\Identity\Actions\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ListUsersRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

#[Group('Users', weight: 20)]
class UserController extends Controller
{
    #[Endpoint(title: 'List users', description: 'Requires permission users.view. Search with q (name or email), filter by status and role, sort with sort (name, email, created_at, last_login_at; prefix - for descending), page with page and per_page (max 100).')]
    public function index(ListUsersRequest $request, ListUsers $listUsers): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection($listUsers->execute($request->validated()));
    }

    #[Endpoint(title: 'Show a user', description: 'Requires permission users.view.')]
    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    #[Endpoint(title: 'Update a user\'s name', description: 'Requires permission users.update. Only super_admin can edit a super_admin.')]
    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        Gate::authorize('update', $user);

        /** @var User $actor */
        $actor = $request->user();

        $updateUser->execute($user, $request->string('name')->toString(), $actor);

        return new UserResource($user->load('roles'));
    }
}

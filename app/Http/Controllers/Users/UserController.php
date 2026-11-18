<?php

namespace App\Http\Controllers\Users;

use App\Domain\Identity\Actions\ListUsers;
use App\Domain\Identity\Actions\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ListUsersRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(ListUsersRequest $request, ListUsers $listUsers): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection($listUsers->execute($request->validated()));
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user->load('roles'));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $updateUser): UserResource
    {
        Gate::authorize('update', $user);

        $updateUser->execute($user, $request->string('name')->toString());

        return new UserResource($user->load('roles'));
    }
}

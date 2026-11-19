<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Authentication', weight: 10)]
class MeController extends Controller
{
    #[Endpoint(title: 'Current user', description: 'The signed-in user with their roles and the permissions the UI can use to show or hide screens. The API enforces permissions on every request regardless.')]
    public function __invoke(Request $request): CurrentUserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CurrentUserResource($user->load('roles'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\CurrentUserResource;
use App\Models\User;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): CurrentUserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new CurrentUserResource($user->load('roles'));
    }
}

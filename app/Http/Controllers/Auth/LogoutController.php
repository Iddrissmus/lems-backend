<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\LogoutUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Authentication', weight: 10)]
class LogoutController extends Controller
{
    #[Endpoint(title: 'Sign out', description: 'Revokes the token used for this request. Other devices stay signed in.')]
    public function __invoke(Request $request, LogoutUser $logout): Response
    {
        /** @var User $user */
        $user = $request->user();

        $logout->execute($user);

        return response()->noContent();
    }
}

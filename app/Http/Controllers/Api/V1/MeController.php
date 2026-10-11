<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group('Authentication', 'Issue and revoke API tokens.', weight: 0)]
class MeController extends Controller
{
    /**
     * Show the signed-in user.
     */
    public function __invoke(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('govEntity'));
    }
}

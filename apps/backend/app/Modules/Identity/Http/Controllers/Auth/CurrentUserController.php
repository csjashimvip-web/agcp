<?php

namespace Modules\Identity\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Modules\Identity\Http\Resources\UserResource;

class CurrentUserController
{
    public function __invoke(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}

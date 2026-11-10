<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    /**
     * Authorize a given action for the current user.
     */
    public function authorize(string $ability, mixed $arguments = []): mixed
    {
        return Gate::authorize($ability, $arguments);
    }
}

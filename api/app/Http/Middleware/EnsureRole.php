<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $actor = $request->user();
        $role = $actor && method_exists($actor, 'getRole') ? $actor->getRole() : null;

        abort_unless($actor && in_array($role, $roles, true), 403, 'This account cannot access the requested area.');

        return $next($request);
    }
}

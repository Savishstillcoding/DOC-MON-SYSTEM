<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the request through only if the logged-in user has the given role.
 * Used in routes/web.php as 'role:officer', 'role:signatory' or 'role:admin'.
 */
class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if ($request->user()?->role !== $role) {
            abort(403);   // logged in, but this page belongs to another role
        }

        return $next($request);
    }
}

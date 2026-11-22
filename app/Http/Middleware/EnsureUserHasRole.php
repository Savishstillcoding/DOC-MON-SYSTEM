<?php

namespace App\Http\Middleware;

/*
| READING GUIDE 2/15: EnsureUserHasRole
|
| WHAT: a middleware (a check that runs before a page opens).
| USED FOR: blocking users from other roles' pages (an officer opening
|           /admin/users gets "403 Forbidden").
| KEY IDEA: your role ≠ the page's role → blocked; same → let through.
*/

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserHasRole = the guard at each dashboard's door.
 * Used in routes/web.php as 'role:officer', 'role:signatory' or 'role:admin'.
 */
class EnsureUserHasRole
{
    /**
     * handle() = runs before every page that has 'role:...'.
     * How it works:
     *   1. Compare YOUR role with the role the PAGE needs.
     *   2. Different → stop with "403 Forbidden".
     *   3. Same → let the request through to the page.
     *
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

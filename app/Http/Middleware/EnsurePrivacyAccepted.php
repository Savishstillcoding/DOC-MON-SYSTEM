<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts that existed before the privacy notice never saw it (AUD-078), so they
 * are asked once: until they accept, every dashboard page sends them to /privacy.
 * Used in routes/web.php as 'privacy'.
 */
class EnsurePrivacyAccepted
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->privacy_accepted_at === null) {
            return redirect('/privacy');
        }

        return $next($request);
    }
}

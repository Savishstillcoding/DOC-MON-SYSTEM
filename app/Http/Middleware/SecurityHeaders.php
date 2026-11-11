<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every page (AUD-069).
 *
 *  - No framing: another site can't show DOC-MON inside an invisible frame and trick a
 *    signatory into clicking "Approve" (clickjacking). X-Frame-Options for old browsers,
 *    CSP frame-ancestors for new ones. DOC-MON never frames its own pages either.
 *  - nosniff: the browser uses the type the server says, never guesses (an upload can't
 *    be run as a script).
 *  - Referrer: other sites see only "doc-mon-system.onrender.com", never full URLs with ticket numbers.
 *  - HSTS (live HTTPS only): the browser refuses plain http:// for a year.
 *
 * A full Content-Security-Policy is left out on purpose: the pages use inline scripts and styles.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if ($request->isSecure() && app()->environment('production')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            // Keep a header a controller set on purpose (e.g. the ID photo's own nosniff)
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\PublicUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the site's own https address is known (APP_URL), every request is treated as if it was sent to that host.
 *
 * A request may carry a made-up host in the Host header or in X-Forwarded-Host. Nothing may depend on it: not the links in
 * our emails (PublicUrl::pin() takes care of those) and not the check that a signed link is genuine, which compares the link
 * with the address the request arrived at. Setting the host here, first of all, keeps both right whichever way the hosting
 * proxy passes the host on. With no https APP_URL (local work, tests) this does nothing.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = PublicUrl::host();

        if ($host !== null) {
            $request->headers->remove('X-Forwarded-Host');
            $request->headers->set('Host', $host);
            $request->server->set('HTTP_HOST', $host);
        }

        return $next($request);
    }
}

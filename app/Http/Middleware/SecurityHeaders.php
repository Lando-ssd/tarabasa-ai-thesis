<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The headers every response should carry (found missing by the system audit):
 *
 *  - nosniff: a browser must not guess that a file is a script;
 *  - frame protection: the app cannot be shown inside another site's page (clickjacking);
 *  - referrer policy: other sites are not told which page a child or parent came from;
 *  - permissions policy: only this site may use the microphone (the reading screens need it), and
 *    nothing may use the camera, location or payment features;
 *  - HSTS on a secure connection: the browser keeps using https;
 *  - a Secure session cookie whenever the request came over https;
 *  - no-store for a page shown to someone who is signed in: on a school or family device shared by
 *    several children, pressing Back after Log out must not bring up the last person's page.
 *
 * Fonts may only come from this site (font-src): the typefaces are served from /fonts, so no visitor's address is sent to an outside font
 * service, and a page that tries to load one again shows plain text instead of silently leaking.
 *
 * A full content security policy is not set: the screens use inline scripts and styles throughout,
 * and a policy loose enough to allow them adds little. Only the parts that cost nothing are set.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // The session cookie is marked Secure whenever the request really came over https (behind the
        // host's proxy too), unless SESSION_SECURE_COOKIE says otherwise. Left unset, Laravel sends it
        // without the flag, so a browser would also send it over plain http. This runs before the
        // session starts, which is when the flag is read.
        config(['session.secure' => config('session.secure') ?? $request->isSecure()]);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // The camera is only for the QR scanners: the Teacher's Add learner (class screens) and a child's "Scan my card" on
        // the two login pages. Everywhere else it stays off.
        $camera = $request->is('teacher/classes', 'teacher/classes/*', 'learner/login', 'login') ? '(self)' : '()';
        $response->headers->set('Permissions-Policy', "microphone=(self), camera={$camera}, geolocation=(), payment=(), usb=(), interest-cohort=()");
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'; font-src 'self' data:");

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000');
        }

        // A file, and a photo (a child's or an adult's own; their controllers set their own short, private cache time and a version
        // in the address), are not "pages": without this every photo on a list would be downloaded again on every page.
        $isFile = $response instanceof BinaryFileResponse || $response instanceof StreamedResponse || $request->routeIs('photos.learner', 'photos.user');
        if (! $isFile && (Auth::guard('web')->check() || Auth::guard('learner')->check())) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}

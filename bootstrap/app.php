<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS at its own edge proxy and forwards plain
        // HTTP internally — without this, Laravel can't tell the request
        // was actually HTTPS, which breaks secure-cookie flags and any
        // absolute URL generation that checks $request->secure().
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'teacher' => \App\Http\Middleware\EnsureUserIsTeacher::class,
            'teacher.active' => \App\Http\Middleware\EnsureTeacherIsActive::class,
            'parent' => \App\Http\Middleware\EnsureUserIsParent::class,
            'learner.auth' => \App\Http\Middleware\EnsureLearnerLoggedIn::class,
            'learner.diagnostic' => \App\Http\Middleware\EnsureDiagnosticComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

<?php

use App\Http\Middleware\EnsureActiveOrganization;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        // The SPA owns the login screen, so there is no named `login` route on
        // the backend. Without this, a guest hitting an auth-protected URL in
        // the browser (e.g. a certificate download opened in a new tab after
        // the session expired) crashes with "Route [login] not defined"
        // instead of being sent to sign in. JSON/XHR requests still get 401.
        $middleware->redirectGuestsTo('/login');

        // The app runs behind a reverse proxy (Caddy) in production —
        // honour X-Forwarded-* so https URLs and signed links validate.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => EnsureRole::class,
            'org.active' => EnsureActiveOrganization::class,
            'feature' => EnsureFeature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

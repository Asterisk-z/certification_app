<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate an organization feature (templates, groups, recipients, certificates).
 * Admins are never restricted; org users are blocked when their organization
 * isn't granted the feature. A no-op when applied to admin routes.
 */
class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $next($request);
        }

        if (! $user?->organization?->allows($feature)) {
            abort(403, "Your organization does not have access to {$feature}.");
        }

        return $next($request);
    }
}

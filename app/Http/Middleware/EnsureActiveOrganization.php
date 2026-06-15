<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the organization portal: the authenticated org user must belong to an
 * organization that still exists and is active. Catches an org being
 * deactivated or removed mid-session.
 */
class EnsureActiveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->user()?->organization;

        if (! $organization || ! $organization->isActive()) {
            abort(403, 'This organization account is inactive.');
        }

        return $next($request);
    }
}

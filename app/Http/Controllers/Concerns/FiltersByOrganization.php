<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FiltersByOrganization
{
    /**
     * Let an admin narrow a tenant listing to one organization via
     * ?organization={uuid}. Ignored for non-admins (their global scope already
     * pins them to their own org).
     */
    protected function applyOrganizationFilter(Builder $query, Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            return;
        }

        if ($uuid = $request->query('organization')) {
            $id = Organization::where('uuid', $uuid)->value('id');
            abort_if(! $id, 404, 'Unknown organization.');
            $query->where($query->getModel()->getTable().'.organization_id', $id);
        }
    }
}

<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation for org-owned models. When (and only when) the authenticated
 * user is an organization user, every query is constrained to that org and new
 * records inherit its organization_id. Admins, recipients, guests and queue
 * workers are never scoped — they see everything (the public verifier, the
 * recipient portal and background jobs must keep working across orgs).
 *
 * Note: issuance happens in non-request contexts (queues) where there is no
 * auth user, so services that create certificates set organization_id
 * explicitly rather than relying on the creating hook here.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        // Registered under this trait's name so callers can lift the scope with
        // withoutGlobalScope(BelongsToOrganization::class) — e.g. the global
        // certificate-number uniqueness probe. (Note: a bare self::class inside
        // a trait resolves to the using model, so name it explicitly.)
        static::addGlobalScope(BelongsToOrganization::class, function (Builder $builder) {
            if ($organizationId = self::currentOrganizationId()) {
                $builder->where($builder->getModel()->getTable().'.organization_id', $organizationId);
            }
        });

        static::creating(function (Model $model) {
            if (! $model->organization_id && $organizationId = self::currentOrganizationId()) {
                $model->organization_id = $organizationId;
            }
        });
    }

    /**
     * The org to scope to, or null when the current actor is not an org user.
     */
    protected static function currentOrganizationId(): ?int
    {
        $user = auth()->user();

        if ($user && $user->role === UserRole::Organization && $user->organization_id) {
            return $user->organization_id;
        }

        return null;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}

<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The recipient listing's filters, shared by the index (what the admin sees)
 * and the bulk action (so "select all matching" acts on exactly that set).
 *
 * Keys: q, name, email, organization_name, organization (uuid), group (uuid),
 * certificates (none|some), portal (active|not_invited). Unknown keys and
 * empty values are ignored.
 */
class RecipientListFilter
{
    public const KEYS = ['q', 'name', 'email', 'organization_name', 'organization', 'group', 'certificates', 'portal'];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters, User $user): Builder
    {
        $text = fn (string $key) => trim((string) ($filters[$key] ?? ''));

        if ($search = $text('q')) {
            $like = $this->like($search);
            $query->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('email', 'like', $like));
        }

        if ($name = $text('name')) {
            $query->where('full_name', 'like', $this->like($name));
        }

        if ($email = $text('email')) {
            $query->where('email', 'like', $this->like($email));
        }

        // Organization filters are admin-only; an org user's global scope
        // already pins them to their own tenant.
        if ($user->isAdmin()) {
            if ($orgName = $text('organization_name')) {
                $query->whereHas('organization', fn ($q) => $q->where('name', 'like', $this->like($orgName)));
            }

            if ($orgUuid = $text('organization')) {
                $id = Organization::where('uuid', $orgUuid)->value('id');
                abort_if(! $id, 404, 'Unknown organization.');
                $query->where('recipients.organization_id', $id);
            }
        }

        if ($groupUuid = $text('group')) {
            $query->whereHas('groups', fn ($q) => $q->where('uuid', $groupUuid));
        }

        match ($filters['certificates'] ?? null) {
            'none' => $query->whereDoesntHave('certificates'),
            'some' => $query->whereHas('certificates'),
            default => null,
        };

        match ($filters['portal'] ?? null) {
            'active' => $query->whereNotNull('user_id'),
            'not_invited' => $query->whereNull('user_id'),
            default => null,
        };

        return $query;
    }

    private function like(string $value): string
    {
        return '%'.str_replace(['%', '_'], ['\\%', '\\_'], $value).'%';
    }
}

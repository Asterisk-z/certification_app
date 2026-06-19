<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the per-organization usage caps configured by the platform admin
 * (Organization::LIMITS, stored on `organizations.limits`).
 *
 * Every check is a no-op when the acting user is a platform admin or has no
 * organization — admins bypass limits, mirroring EnsureFeature. Breaches throw
 * a ValidationException (HTTP 422) so the SPA surfaces the message inline.
 */
class OrganizationLimitService
{
    /**
     * Human-readable labels for the limited resources, used in error messages.
     */
    private const LABELS = [
        'certificates' => 'credentials',
        'certificate_admins' => 'certificate admins',
        'templates' => 'templates',
        'groups' => 'groups',
        'recipients' => 'recipients',
    ];

    /**
     * Guard a total-count cap (templates, groups, recipients, certificates).
     */
    public function assertCanCreate(User $actor, string $key, int $adding = 1): void
    {
        $org = $this->orgFor($actor);

        if (! $org || $adding < 1) {
            return;
        }

        $cap = $org->limitFor($key);

        if ($cap === null) {
            return;
        }

        $current = match ($key) {
            'templates' => $org->templates()->count(),
            'groups' => $org->groups()->count(),
            'recipients' => $org->recipients()->count(),
            'certificates' => $org->certificates()->active()->count(),
            default => 0,
        };

        if ($current + $adding > $cap) {
            $this->fail($key, "Your organization's limit of {$cap} ".$this->label($key)." has been reached.");
        }
    }

    /**
     * Guard the number of organization (certificate-admin) login users.
     */
    public function assertCanInviteAdmin(User $actor, int $adding = 1): void
    {
        $org = $this->orgFor($actor);

        if (! $org) {
            return;
        }

        $cap = $org->limitFor('certificate_admins');

        if ($cap === null) {
            return;
        }

        $current = $org->users()->where('role', UserRole::Organization)->count();

        if ($current + $adding > $cap) {
            $this->fail('email', "Your organization's limit of {$cap} certificate admins has been reached.");
        }
    }

    /**
     * Guard how many credentials a single recipient may hold.
     */
    public function assertCertificatesPerRecipient(User $actor, Recipient $recipient, int $adding = 1): void
    {
        $org = $this->orgFor($actor);

        if (! $org || $adding < 1) {
            return;
        }

        $cap = $org->limitFor('certificates_per_recipient');

        if ($cap === null) {
            return;
        }

        $current = $recipient->certificates()->active()->count();

        if ($current + $adding > $cap) {
            $this->fail(
                'recipient',
                "{$recipient->full_name} already holds the maximum of {$cap} credential(s) allowed per recipient."
            );
        }
    }

    /**
     * Guard how many credentials may be tied to a single group.
     */
    public function assertCertificatesPerGroup(User $actor, ?Group $group, int $adding = 1): void
    {
        $org = $this->orgFor($actor);

        if (! $org || ! $group || $adding < 1) {
            return;
        }

        $cap = $org->limitFor('certificates_per_group');

        if ($cap === null) {
            return;
        }

        $current = $group->certificates()->active()->count();

        if ($current + $adding > $cap) {
            $this->fail(
                'group',
                "The group \"{$group->name}\" already holds the maximum of {$cap} credential(s) allowed per group."
            );
        }
    }

    /**
     * Guard how many groups a single recipient may belong to. $targetCount is
     * the recipient's resulting group membership after the assignment.
     */
    public function assertGroupsPerRecipient(User $actor, Recipient $recipient, int $targetCount): void
    {
        $org = $this->orgFor($actor);

        if (! $org) {
            return;
        }

        $cap = $org->limitFor('groups_per_recipient');

        if ($cap === null) {
            return;
        }

        if ($targetCount > $cap) {
            $this->fail(
                'group_uuids',
                "A recipient may belong to at most {$cap} group(s); {$recipient->full_name} would exceed that."
            );
        }
    }

    /**
     * Aggregate guard for spreadsheet imports: each row becomes one credential
     * for one recipient. Checks the org-total credential cap, the per-group cap,
     * and the recipients cap (counting only emails that would be newly active).
     * Per-recipient/per-group nuances within a single file are left to the
     * interactive paths.
     *
     * @param  Collection<int, mixed>  $rows  heading-row collections from the importer
     */
    public function assertImportWithinLimits(User $actor, Collection $rows, ?Group $group = null): void
    {
        if (! $this->orgFor($actor)) {
            return;
        }

        $rowCount = $rows->count();

        $this->assertCanCreate($actor, 'certificates', $rowCount);
        $this->assertCertificatesPerGroup($actor, $group, $rowCount);

        $emails = $rows
            ->map(fn ($row) => strtolower(trim((string) data_get($row, 'email', ''))))
            ->filter()->unique();

        $live = Recipient::whereIn('email', $emails->all())->pluck('email')
            ->map(fn ($e) => strtolower($e))->all();
        $newCount = $emails->reject(fn ($e) => in_array($e, $live, true))->count();

        $this->assertCanCreate($actor, 'recipients', $newCount);
    }

    /**
     * The organization whose caps apply, or null when the actor is exempt
     * (platform admin or no organization).
     */
    private function orgFor(User $actor): ?Organization
    {
        if ($actor->isAdmin()) {
            return null;
        }

        return $actor->organization;
    }

    private function label(string $key): string
    {
        return self::LABELS[$key] ?? $key;
    }

    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}

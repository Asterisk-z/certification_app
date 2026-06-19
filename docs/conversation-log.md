# Conversation Log

A running record of working sessions on this project (credential platform — Laravel 11 + Vue 3).
Each entry captures one conversation/session: what was asked, what was decided, and what changed.

## How to use this log

- Add a new entry at the **top** of the "Sessions" list (most recent first).
- Keep entries short — the goal is a scannable history, not a transcript.
- Reference commits by short hash so an entry links back to the actual diff.
- Use the template below. Leave fields blank rather than deleting them.

```markdown
### YYYY-MM-DD — <short title>

- **Goal:** what the user wanted to accomplish.
- **Decisions:** key choices made and why (skip if none).
- **Changes:** files/areas touched, with commit hashes if committed.
- **Follow-ups:** open items / things deferred (skip if none).
```

---

## Sessions

<!-- newest first -->

### 2026-06-19 — Fix password-reset link for SPA (setup/invite emails)

- **Goal:** Resolve a runtime error surfaced by the new team-invite endpoint: `Route [password.reset] not defined`.
- **Decisions:** Root cause was app-wide — `Password::sendResetLink()` builds the email URL from a named `password.reset` route that this SPA never defines (org setup links + public forgot-password share the latent bug; all tests masked it with `Notification::fake()`). Fixed at the source with `ResetPassword::createUrlUsing()` in `AppServiceProvider::boot()`, pointing at the SPA `/reset-password?token=&email=` route.
- **Changes:** `AppServiceProvider` URL resolver; switched the team-invite tests to `Mail::fake()` so they actually build the URL (would have caught it). All 14 limit tests + org/auth suites green. (The earlier `org() returned null` log line was a stale pre-fix test run.)
- **Follow-ups:** None.

### 2026-06-19 — Admin-configurable per-organization limits

- **Goal:** Let the platform Admin cap each organization's usage (total credentials/templates/groups/recipients/certificate-admins, plus per-recipient and per-group caps). Includes building multi-admin support for orgs.
- **Decisions:** New `limits` JSON column (separate from `features`); enforcement centralized in `OrganizationLimitService` (admins bypass, breaches → 422); certificate caps count only active statuses (Pending/Queued/Sent) so renewal is net-zero; dropped "templates per recipient"; built `OrganizationTeamController` (admin + new `/org/team` self-service) for multiple org logins, primary login protected.
- **Changes:** migration + `Organization`/`Group`/`Certificate` model tweaks; new service + team controller; enforcement wired into Template/Group/Recipient/Bulk/Import controllers, `CertificateController` send/manual, and the two importers; limits validation/persistence in `OrganizationController`; frontend limits UI on org form + detail page, new `org/TeamPage.vue`, OrgLayout nav, router, store actions; `OrganizationLimitTest` (14 tests, all green). Full suite green except one pre-existing date-sensitive renew test (unrelated).
- **Follow-ups:** Per-recipient/per-group caps are not enforced inside bulk/import paths (interactive paths are); `CertificateLifecycleTest::test_renew_creates_new_certificate_with_same_data` fails due to a hardcoded date now in the past — offer to fix separately.

### 2026-06-19 — Verify understanding of core flows

- **Goal:** Confirm I understand the organization, admin, recipient, user, certificate, and template flows.
- **Changes:** No code changes — read the source to verify (User/Recipient models, InviteController, CertificateIssueService, OrganizationController) and explained each flow: single users table with role enum; org creation also provisions an Organization-role user + welcome/setup link; recipients created without a login then claim an account via signed invite; template Draft→Ready on layout save; certificate issue→queue→render(PDF/PNG+QR)→email→Sent, plus renew/expire. Noted tenancy via BelongsToOrganization global scope (admin unscoped; jobs stamp organization_id explicitly).
- **Follow-ups:** Awaiting which flow (if any) to go deeper on or what change to make next.

- **Goal:** Analyze the entire application to understand what it is about.
- **Changes:** No code changes — ran two parallel Explore agents (Laravel backend + Vue frontend) and synthesized a full overview: it's a multi-tenant HSE certificate issuance/verification platform (Laravel 11 API + Vue 3 SPA, Sanctum). Covered the domain model, four role-gated portals (public/admin/org/recipient), the design→issue→render→verify flow, org multi-tenancy scoping, and the stack.
- **Follow-ups:** None — analysis only.

### 2026-06-19 — Automate the conversation log

- **Goal:** Have every request and the actions taken summarized into the log automatically, for future reference.
- **Decisions:** Used a `UserPromptSubmit` hook (in committed `.claude/settings.json`) that injects a standing reminder each turn to append a log entry — the durable, harness-enforced mechanism, vs. relying on memory.
- **Changes:** Added `.claude/settings.json` with the hook; validated JSON + pipe-tested the command output.
- **Follow-ups:** Hook may need a one-time `/hooks` open or restart to register, since `.claude/settings.json` was created mid-session.

### 2026-06-19 — Set up conversation tracking

- **Goal:** Create a means of tracking our conversations for this project.
- **Changes:** Added `docs/conversation-log.md` (this file) with a documented entry format, seeded with prior sessions reconstructed from git history.
- **Follow-ups:** Update this log at the end of each working session.

### 2026-06-16 — Welcome email on organization creation

- **Goal:** Ensure a welcome email always goes out when an organization is created.
- **Changes:** `36ed11d` — Always email a welcome on organization creation.

### 2026-06-15 — Organization multi-tenancy

- **Goal:** Build organization-level multi-tenancy into the credential platform.
- **Decisions:** `BelongsToOrganization` global scope; `/org` portal reuses admin pages with feature gating. (See memory: org-multitenancy.)
- **Changes:** `8ec1b81` — build organization multi-tenancy into the credential platform.

### 2026-06-15 — Manual credential creation flow

- **Goal:** Build out the manual "create credential" flow.
- **Changes:**
  - `3a79d86` — searchable selects with inline group/recipient creation.
  - `2a2b6a5` — optional template, group, and file upload.

### 2026-06-15 — Legacy data migration & seeding

- **Goal:** Bring legacy certificate data into the new platform.
- **Changes:**
  - `9b747d8` — issue legacy credentials to each group's recipients.
  - `9fd9fa0` — seed legacy template block layouts.
  - `29c8cdc` — seed legacy certificate templates.
  - `d0feb31` — seed legacy recipients into their groups.
  - `d566061` — rename GroupSeeder to LegacyDataSeeder.

### 2026-06-15 — On-platform signing

- **Goal:** Support signature blocks signed on the platform.
- **Changes:** `2d14e0f` — add signature block type with on-platform signing.

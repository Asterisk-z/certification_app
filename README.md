# HSE Board Credentials — start.credential.hseboard.com

Certificate / credential management platform: design templates with a drag-and-drop
designer, issue personalised certificates in bulk, deliver them by email with PDF +
QR verification, and give every recipient a self-service portal.

Built with **Laravel 11** (API, queues, PDF rendering) and **Vue 3** (SPA via Vite,
Pinia, Tailwind CSS 4), authenticated with **Sanctum** SPA cookies.

## Features

- **Template designer** — upload a background image (its pixel size defines the
  canvas), then drag/resize text, image and QR-code blocks. Positions are stored in
  template coordinates, so the designer, the public HTML view and the PDF are
  pixel-identical. Saving the layout marks the template **ready to send**.
- **Dynamic fields** — default placeholders (`full_name`, `email`,
  `completion_date`, `issue_date`, `certificate_number`, `qr_code`) plus any custom
  dynamic blocks; resolved per recipient at render time.
- **Recipients & groups** — CRUD, group membership, Excel import with per-template
  format download (headers include the template's dynamic fields), heading
  validation and per-row failure reporting.
- **Issuance lifecycle** — queued sending (one job per certificate, retries with
  backoff), statuses: pending → queued → sent / failed, plus revoked, expired,
  renewed, cancelled. Revoke (+email), un-revoke, resend, renew (same data, new
  number/dates), soft delete with a restorable **Deleted** tab, bulk actions,
  manual creation with custom numbers, manual PDF upload, working server-side
  search that displays properly on mobile.
- **Recipient portal** — signed email invite → set password → dashboard with all
  certificates, inline preview, PDF download.
- **Public verification** — the landing page (`/?number=…`); QR codes printed on
  certificates link straight to it. Reports valid / expired / revoked / renewed /
  not found.
- **Notifications** — in-app bell (database channel) for issued/revoked/expiring
  certificates and admin alerts; expiring-soon email at T-30 days.
- **Newsletters** — compose and send to everyone / certified users / admins,
  batched through the queue.
- **Logs** — mail log for every email (queued/sent/failed + error), full activity
  audit trail (spatie/laravel-activitylog) with causer.
- **Dark mode** — light / dark / system themes with a toggle in every layout header,
  persisted in `localStorage` and applied before first paint (no flash). Class-based
  via Tailwind's `@custom-variant dark`; native form controls follow via
  `color-scheme`. The template designer canvas and rendered certificates always stay
  paper-white, since they represent the printed document.

## Docker (recommended)

The repo ships a full stack — app (PHP-FPM + Chromium for PDFs), nginx, queue
worker, scheduler, MySQL 8 and Mailpit:

```bash
docker compose up -d --build
```

| Service | URL |
| --- | --- |
| App | http://localhost:8080 (admin@hseboard.com / password) |
| Mailpit (all outgoing email) | http://localhost:8025 |
| MySQL (host access) | localhost:33070, user `hse` / `secret` |

`APP_KEY` is read from the project `.env` (generate one with
`docker compose run --rm --no-deps app php artisan key:generate --show`).
The app container migrates, seeds and caches on boot; the queue worker and
scheduler share the same image and a common `storage` volume, so PDFs created
by the worker are served by nginx. Logs: `docker compose logs -f app queue`.

## Local development

Requirements: PHP 8.2+, Composer, Node 20+, Google Chrome (for PDF rendering).

```bash
composer install
npm install                      # puppeteer is installed without bundled Chromium
cp .env.example .env             # then set CHROME_PATH (see below)
php artisan key:generate
php artisan migrate --seed       # seeds admin@hseboard.com / password
php artisan storage:link

# run all three in separate terminals:
php artisan serve
php artisan queue:work --queue=emails,default
npm run dev
```

Default admin login: `admin@hseboard.com` / `password` (change it!).

### Environment notes

| Key | Purpose |
| --- | --- |
| `CHROME_PATH` | Absolute path to Chrome/Chromium for Browsershot, e.g. `'C:/Program Files/Google/Chrome/Application/chrome.exe'` or `/usr/bin/google-chrome`. |
| `SANCTUM_STATEFUL_DOMAINS` | Must include the host(:port) the SPA is served from. |
| `QUEUE_CONNECTION` | `database` (default). A running worker is required for emails/PDFs. |
| `MAIL_MAILER` | `log` in dev (emails land in `storage/logs/laravel.log`); SMTP in production. |

## Production deployment

1. `composer install --no-dev && npm ci && npm run build`
2. Migrate + seed an admin, `php artisan storage:link`, set a real `APP_URL`
   (QR verification links use it) and SMTP credentials.
3. Run a supervised queue worker: `php artisan queue:work --queue=emails,default --tries=3`.
4. Add the scheduler cron: `* * * * * php artisan schedule:run` (daily expiry job +
   expiring-soon notifications run at 00:30).
5. Install Chrome/Chromium on the host and set `CHROME_PATH`
   (`npm install puppeteer` is done with `PUPPETEER_SKIP_DOWNLOAD=true`; the
   bundled-browser route also works if you drop that env var).

## Tests

```bash
php artisan test    # 44 feature tests: auth, templates/designer, import, lifecycle, portal, verification, notifications
```

Tests run on an in-memory SQLite database (see `phpunit.xml`), so they never touch
your dev data.

## Architecture quick map

```
app/Enums              CertificateStatus (state machine), TemplateStatus, DurationType, BlockType, UserRole
app/Services           TemplateService (blocks/layout/duplicate), CertificateNumberService (atomic CODE-000001),
                       PlaceholderResolver, CertificateIssueService (issue/renew/queue), CertificateRenderService (Browsershot)
app/Jobs               SendCertificateJob (tries=3), SendNewsletterJob, ExpireCertificatesJob (scheduled)
app/Http/Controllers   Admin/* (templates, blocks, designer, recipients, groups, import, certificates, actions,
                       upload, newsletters, logs, dashboard), Recipient/PortalController, Public/* (verify, cert view)
resources/js           Vue SPA: pages/admin, pages/recipient, pages/public, components/designer (drag-drop canvas),
                       Pinia stores (auth, templates, certificates, recipients, groups, ui)
resources/views        certificates/render.blade.php (the single source of truth for certificate markup),
                       emails/* (markdown mailables)
```

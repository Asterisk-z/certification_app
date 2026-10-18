<?php

use App\Jobs\ExpireCertificatesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireCertificatesJob)->dailyAt('00:30');

// Re-send outgoing mail that is still queued or failed (see config/mail.php `retry`).
Schedule::command('mail:retry-pending')->everyFiveMinutes()->withoutOverlapping();

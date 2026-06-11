<?php

use App\Jobs\ExpireCertificatesJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new ExpireCertificatesJob)->dailyAt('00:30');

<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This is an SPA: password-reset / account-setup links must point at the
        // frontend route (/reset-password), not a server-rendered `password.reset`
        // route (which does not exist). Covers the public forgot-password flow as
        // well as the org/team setup links sent via Password::sendResetLink().
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return rtrim(config('app.url'), '/')
                .'/reset-password?token='.$token
                .'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }
}

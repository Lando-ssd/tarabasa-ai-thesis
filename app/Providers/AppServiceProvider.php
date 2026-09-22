<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use Illuminate\Support\Facades\Mail;
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
        Mail::extend('gmail-api', function () {
            return new GmailApiTransport(
                config('services.gmail_send.client_id'),
                config('services.gmail_send.client_secret'),
                config('services.gmail_send.refresh_token'),
            );
        });
    }
}

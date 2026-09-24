<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Models\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
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
        // The Parent menu bar shows how many alerts are unread on every Parent page, so the
        // count is worked out once here instead of by each controller.
        View::composer('layouts.parent-shell', function ($view) {
            $view->with('navUnread', auth()->check()
                ? Notification::where('recipient_user_id', auth()->id())->where('is_read', false)->count()
                : 0);
        });

        Mail::extend('gmail-api', function () {
            return new GmailApiTransport(
                config('services.gmail_send.client_id'),
                config('services.gmail_send.client_secret'),
                config('services.gmail_send.refresh_token'),
            );
        });
    }
}

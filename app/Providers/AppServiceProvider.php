<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Models\Notification;
use App\Support\TeacherNav;
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

        // Same for the Teacher menu bar: unread alerts and learners waiting to be claimed.
        View::composer('layouts.teacher-shell', function ($view) {
            $counts = auth()->check() ? TeacherNav::counts(auth()->user()) : ['unread' => 0, 'claim' => 0];
            $view->with('navUnread', $counts['unread'])->with('navClaim', $counts['claim']);
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

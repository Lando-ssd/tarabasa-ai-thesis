<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use App\Models\Notification;
use App\Queue\PlainLockDatabaseConnector;
use App\Support\TeacherNav;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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

        // And the Admin menu: teachers waiting for approval and services with a real problem.
        View::composer('layouts.admin-shell', function ($view) {
            $counts = auth()->check() ? \App\Support\AdminNav::counts() : ['waiting' => 0, 'health' => 0];
            $view->with('navWaiting', $counts['waiting'])->with('navHealth', $counts['health']);
        });

        // Laravel's database queue connector, with one optional switch (QUEUE_POP_LOCK=plain) for a
        // database that has no "FOR UPDATE SKIP LOCKED" (TiDB). Without the switch it returns
        // Laravel's own queue, so nothing changes on any other host.
        $this->app->afterResolving('queue', function ($manager) {
            $manager->extend('database', fn () => new PlainLockDatabaseConnector($this->app['db']));
        });

        // "A scoring worker is alive" signal, so a waiting recording is only ever scored inside a web request
        // when no worker has been seen at all (see BackgroundScoring::refresh).
        \App\Services\BackgroundScoring::listen();

        // The child's reading screens ask quick questions while a recording is being sent and scored. They are counted
        // per CHILD: counted per address, a whole class behind the school's one internet address shared a single allowance
        // and the children after the first few were turned away.
        $perChild = fn (Request $request) => $request->user('learner')?->id ? 'learner:'.$request->user('learner')->id : $request->ip();
        RateLimiter::for('scoring-status', fn (Request $request) => Limit::perMinute(120)->by($perChild($request)));
        RateLimiter::for('learner-ready', fn (Request $request) => Limit::perMinute(60)->by($perChild($request)));
        RateLimiter::for('learner-warm', fn (Request $request) => Limit::perMinute(20)->by($perChild($request)));
        RateLimiter::for('learner-game-check', fn (Request $request) => Limit::perMinute(90)->by($perChild($request)));

        Mail::extend('gmail-api', function () {
            return new GmailApiTransport(
                config('services.gmail_send.client_id'),
                config('services.gmail_send.client_secret'),
                config('services.gmail_send.refresh_token'),
            );
        });
    }
}

<?php

use App\Http\Controllers\Api\AccountApiController;
use App\Http\Controllers\Api\LearnerApiController;
use App\Http\Controllers\Api\LearnerBookshelfApiController;
use App\Http\Controllers\Api\LearnerDiagnosticApiController;
use App\Http\Controllers\Api\LearnerGamesApiController;
use App\Http\Controllers\Api\LearnerReadingApiController;
use App\Http\Controllers\LearnerReadingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile Learner API — Sanctum token auth, no session cookie involved.
|--------------------------------------------------------------------------
|
| Learner-only, matching the mobile app's own scope (Teacher/Parent/Admin
| stay web-only). These routes are thin wrappers over the exact same
| services the web Blade controllers call (LearnerReadingService,
| LearnerDiagnosticService) — see app/Services/ — so scoring, mastery,
| and adaptive-recommendation math is never duplicated between web and
| mobile. 'auth:sanctum' renders a real 401 JSON response on failure
| (bootstrap/app.php already forces JSON rendering for any api/* request),
| no redirect — a mobile client has no concept of a Blade login redirect.
*/

Route::post('/learner/login', [LearnerApiController::class, 'login'])->name('api.learner.login');

// Teacher and Parent sign in and sign up (email and password, the website's own rules). A Learner signs in with code and PIN above.
// An Admin is never accepted here: Admin screens are on the website only.
Route::prefix('auth')->name('api.auth.')->group(function () {
    Route::post('/login', [AccountApiController::class, 'login'])->middleware('throttle:30,1')->name('login');
    Route::post('/register/teacher', [AccountApiController::class, 'registerTeacher'])->middleware('throttle:10,1')->name('register.teacher');
    Route::post('/register/parent', [AccountApiController::class, 'registerParent'])->middleware('throttle:10,1')->name('register.parent');
    Route::post('/resend-verification', [AccountApiController::class, 'resendVerification'])->middleware('throttle:10,1')->name('resend');
    Route::post('/forgot-password', [AccountApiController::class, 'forgotPassword'])->middleware('throttle:10,1')->name('forgot');

    Route::middleware(['auth:sanctum', 'token.actor:teacher,parent'])->group(function () {
        Route::get('/me', [AccountApiController::class, 'me'])->name('me');
        Route::post('/logout', [AccountApiController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['auth:sanctum', 'token.actor:learner'])->prefix('learner')->name('api.learner.')->group(function () {
    Route::post('/logout', [LearnerApiController::class, 'logout'])->name('logout');

    // Diagnostic — deliberately NOT gated by the diagnostic-complete check,
    // same reasoning as the web routes: this is how a Learner completes it.
    Route::get('/diagnostic/passage', [LearnerDiagnosticApiController::class, 'passage'])->name('diagnostic.passage');
    Route::post('/diagnostic/record', [LearnerDiagnosticApiController::class, 'submitRecording'])->name('diagnostic.record');

    Route::middleware('learner.diagnostic.api')->group(function () {
        Route::get('/dashboard', [LearnerApiController::class, 'dashboard'])->name('dashboard');
        Route::get('/activities', [LearnerApiController::class, 'activities'])->name('activities.index');
        Route::get('/activities/{activity}', [LearnerApiController::class, 'showActivity'])->name('activities.show');
        Route::post('/activities/{activity}/record', [LearnerReadingApiController::class, 'submitRecording'])->name('activities.record');
        // The two free practice tries before the real reading (scored for feedback only, nothing saved).
        Route::post('/activities/{activity}/practice', [LearnerReadingApiController::class, 'submitPractice'])->name('activities.practice');

        // My Shelf: the stories the child has really read, and a free re-read of one (unscored, nothing saved).
        Route::get('/bookshelf', [LearnerBookshelfApiController::class, 'index'])->name('bookshelf.index');
        Route::get('/bookshelf/{activity}', [LearnerBookshelfApiController::class, 'show'])->name('bookshelf.show');
        Route::post('/bookshelf/{activity}/reread', [LearnerBookshelfApiController::class, 'reread'])->name('bookshelf.reread');

        // Practice Games (free play, all read-aloud): what the Games screen needs, what each game offers, something read aloud, and "a game ended".
        Route::get('/games', [LearnerGamesApiController::class, 'index'])->name('games.index');
        Route::get('/games/words', [LearnerGamesApiController::class, 'words'])->name('games.words');
        Route::get('/games/train', [LearnerGamesApiController::class, 'train'])->name('games.train');
        Route::get('/games/sentences', [LearnerGamesApiController::class, 'sentences'])->name('games.sentences');
        Route::post('/games/check-text', [LearnerGamesApiController::class, 'checkText'])->middleware('throttle:90,1')->name('games.check-text');
        Route::post('/games/check-word', [LearnerGamesApiController::class, 'checkWord'])->middleware('throttle:90,1')->name('games.check-word');
        Route::post('/games/{game}/finish', [LearnerGamesApiController::class, 'finish'])->middleware('throttle:30,1')->name('games.finish');
    });

    Route::post('/reading-preferences/font-step', [LearnerApiController::class, 'updateReadingFontStep'])->name('reading-preferences.font-step');

    // Wakes the scoring service (and the recommender) as a reading screen opens, same as the website's /learner/warm.
    Route::get('/warm', [LearnerReadingController::class, 'warm'])->middleware('throttle:20,1')->name('warm');
});

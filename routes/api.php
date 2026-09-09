<?php

use App\Http\Controllers\Api\LearnerApiController;
use App\Http\Controllers\Api\LearnerDiagnosticApiController;
use App\Http\Controllers\Api\LearnerReadingApiController;
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

Route::middleware('auth:sanctum')->prefix('learner')->name('api.learner.')->group(function () {
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
    });

    Route::post('/reading-preferences/font-step', [LearnerApiController::class, 'updateReadingFontStep'])->name('reading-preferences.font-step');
});

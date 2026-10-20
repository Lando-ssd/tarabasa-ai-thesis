<?php

use App\Http\Controllers\Api\AccountApiController;
use App\Http\Controllers\Api\LearnerApiController;
use App\Http\Controllers\Api\LearnerBookshelfApiController;
use App\Http\Controllers\Api\LearnerDiagnosticApiController;
use App\Http\Controllers\Api\LearnerGamesApiController;
use App\Http\Controllers\Api\LearnerReadingApiController;
use App\Http\Controllers\Api\ParentApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\TeacherActivityApiController;
use App\Http\Controllers\Api\TeacherMoreApiController;
use App\Http\Controllers\Api\TeacherApiController;
use App\Http\Controllers\ActivityController;
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

        // Their own details: name and contact number, password, and (a Teacher) the grades they handle.
        Route::put('/profile', [ProfileApiController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileApiController::class, 'password'])->middleware('throttle:10,1')->name('profile.password');
        Route::put('/profile/grades', [ProfileApiController::class, 'grades'])->name('profile.grades');
        Route::post('/profile/grades/cancel', [ProfileApiController::class, 'cancelGrades'])->name('profile.grades.cancel');
        // Deleting their own account: what it would do (shown first), then the deletion itself (password again).
        Route::get('/account/erase-info', [ProfileApiController::class, 'eraseInfo'])->name('account.erase-info');
        Route::post('/profile/delete', [ProfileApiController::class, 'destroy'])->middleware('throttle:10,1,erase-account')->name('profile.delete');
    });
});

// The Teacher's screens in the phone app. Looking is open to a Pending Teacher (like the website); anything that changes
// something needs an Active one ('teacher.active' answers a Pending Teacher with a JSON 403 and the website's own words).
Route::middleware(['auth:sanctum', 'token.actor:teacher'])->prefix('teacher')->name('api.teacher.')->group(function () {
    Route::get('/home', [TeacherApiController::class, 'home'])->name('home');
    Route::get('/classes', [TeacherApiController::class, 'classes'])->name('classes.index');
    Route::get('/classes/{class}', [TeacherApiController::class, 'showClass'])->whereNumber('class')->name('classes.show');
    Route::get('/classes/{class}/learners/{learner}', [TeacherApiController::class, 'showLearner'])->whereNumber(['class', 'learner'])->name('classes.learners.show');

    // Alerts (who needs support, who is ready to move up), the Analytics overview, and Promotions: looking is open to a Pending Teacher.
    Route::get('/alerts', [TeacherMoreApiController::class, 'alerts'])->name('alerts.index');
    Route::post('/alerts/{learner}/handled', [TeacherMoreApiController::class, 'handled'])->whereNumber('learner')->name('alerts.handled');
    Route::get('/analytics', [TeacherMoreApiController::class, 'analytics'])->name('analytics');
    Route::get('/promotions', [TeacherMoreApiController::class, 'promotions'])->name('promotions.index');

    // Activities: the review list, My activities and one activity. Asking for new ones is open to a Pending Teacher too (the 2 free
    // credits, Teacher Actor Prompt Step 3); the request is written in the background and these routes follow it.
    Route::get('/activities', [TeacherActivityApiController::class, 'index'])->name('activities.index');
    Route::get('/activities/generate-options', [TeacherActivityApiController::class, 'generateOptions'])->name('activities.generate-options');
    Route::get('/activities/basis', [ActivityController::class, 'basis'])->name('activities.basis');
    Route::get('/activities/warm', [ActivityController::class, 'warm'])->name('activities.warm');
    Route::post('/activities/generate', [TeacherActivityApiController::class, 'generate'])->middleware('throttle:20,1')->name('activities.generate');
    Route::get('/activities/generations/{generation}', [ActivityController::class, 'generationStatus'])->whereNumber('generation')->name('activities.generation');
    Route::post('/activities/generations/{generation}/dismiss', [ActivityController::class, 'dismissGeneration'])->whereNumber('generation')->name('activities.generation.dismiss');
    Route::get('/activities/{activity}', [TeacherActivityApiController::class, 'show'])->whereNumber('activity')->name('activities.show');

    Route::middleware('teacher.active')->group(function () {
        Route::post('/activities/{activity}/place', [TeacherActivityApiController::class, 'place'])->whereNumber('activity')->name('activities.place');
        Route::post('/activities/{activity}/restore', [TeacherActivityApiController::class, 'restore'])->whereNumber('activity')->name('activities.restore');
        Route::put('/activities/{activity}', [TeacherActivityApiController::class, 'edit'])->whereNumber('activity')->name('activities.edit');
        Route::post('/activities/{activity}/assign', [TeacherActivityApiController::class, 'assign'])->whereNumber('activity')->name('activities.assign');
        Route::post('/activities/{activity}/share', [TeacherActivityApiController::class, 'share'])->whereNumber('activity')->name('activities.share');
        Route::post('/classes/{class}/assign', [TeacherActivityApiController::class, 'assignToClass'])->whereNumber('class')->name('classes.assign');

        Route::post('/alerts/{learner}/assign', [TeacherMoreApiController::class, 'assignSuggested'])->whereNumber('learner')->name('alerts.assign');
        Route::post('/promotions/{learner}/release', [TeacherMoreApiController::class, 'release'])->whereNumber('learner')->name('promotions.release');
        Route::post('/promotions/{record}/claim', [TeacherMoreApiController::class, 'claim'])->whereNumber('record')->name('promotions.claim');

        Route::post('/classes', [TeacherApiController::class, 'storeClass'])->name('classes.store');
        Route::put('/classes/{class}', [TeacherApiController::class, 'updateClass'])->whereNumber('class')->name('classes.update');
        Route::post('/classes/{class}/learners', [TeacherApiController::class, 'joinLearner'])->whereNumber('class')->middleware('throttle:60,1')->name('classes.learners.join');
        Route::post('/classes/{class}/learners/check', [TeacherApiController::class, 'checkLearner'])->whereNumber('class')->middleware('throttle:120,1,check-learner')->name('classes.learners.check');
        Route::post('/classes/{class}/learners/{learner}/move', [TeacherApiController::class, 'moveLearner'])->whereNumber(['class', 'learner'])->name('classes.learners.move');
    });
});

// The Parent's screens in the phone app. A Parent has no approval step, so everything here is open to a signed in Parent, and only
// ever reaches the children linked to their own account.
Route::middleware(['auth:sanctum', 'token.actor:parent'])->prefix('parent')->name('api.parent.')->group(function () {
    // A guardian makes a new PIN for a child who forgot theirs (password again, 3 changes a day, no weak PINs: LearnerPinService).
    Route::post('/children/{learner}/pin', [ParentApiController::class, 'resetPin'])->whereNumber('learner')->middleware('throttle:20,1')->name('children.pin');
    // A guardian removes a child's profile (password again; with another guardian linked it only unlinks this one).
    Route::post('/children/{learner}/remove', [ParentApiController::class, 'removeChild'])->whereNumber('learner')->middleware('throttle:20,1,erase-child')->name('children.remove');

    Route::get('/home', [ParentApiController::class, 'home'])->name('home');

    Route::get('/children', [ParentApiController::class, 'children'])->name('children.index');
    Route::get('/children/options', [ParentApiController::class, 'childOptions'])->name('children.options');
    Route::post('/children', [ParentApiController::class, 'storeChild'])->middleware('throttle:10,1')->name('children.store');
    Route::post('/children/link', [ParentApiController::class, 'linkChild'])->middleware('throttle:20,1')->name('children.link');
    Route::get('/children/{learner}/card', [ParentApiController::class, 'card'])->whereNumber('learner')->name('children.card');

    Route::get('/progress', [ParentApiController::class, 'progress'])->name('progress');

    Route::get('/repository', [ParentApiController::class, 'repository'])->name('repository.index');
    Route::post('/repository/{listing}/unlock', [ParentApiController::class, 'unlock'])->whereNumber('listing')->name('repository.unlock');
    Route::post('/repository/{listing}/rate', [ParentApiController::class, 'rate'])->whereNumber('listing')->name('repository.rate');

    Route::get('/alerts', [ParentApiController::class, 'alerts'])->name('alerts.index');
    Route::post('/alerts/read-all', [ParentApiController::class, 'markAllRead'])->name('alerts.read-all');
    Route::post('/alerts/{notification}/read', [ParentApiController::class, 'markRead'])->whereNumber('notification')->name('alerts.read');
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
        Route::get('/games/syllables', [LearnerGamesApiController::class, 'syllables'])->name('games.syllables');
        Route::post('/games/check-text', [LearnerGamesApiController::class, 'checkText'])->middleware('throttle:90,1')->name('games.check-text');
        Route::post('/games/check-word', [LearnerGamesApiController::class, 'checkWord'])->middleware('throttle:90,1')->name('games.check-word');
        Route::post('/games/{game}/finish', [LearnerGamesApiController::class, 'finish'])->middleware('throttle:30,1')->name('games.finish');
    });

    Route::post('/reading-preferences/font-step', [LearnerApiController::class, 'updateReadingFontStep'])->name('reading-preferences.font-step');

    // Wakes the scoring service (and the recommender) as a reading screen opens, same as the website's /learner/warm.
    Route::get('/warm', [LearnerReadingController::class, 'warm'])->middleware('throttle:20,1')->name('warm');
});

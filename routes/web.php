<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\LearnerAuthController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LearnerDiagnosticController;
use App\Http\Controllers\LearnerReadingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\TeacherDashboardController;
use Illuminate\Support\Facades\Route;

// Real homepage — role select screen, replaces Laravel's default welcome page.
Route::get('/', function () {
    return view('landing');
})->name('landing');

Route::get('/register/teacher', [AuthController::class, 'showTeacherRegister'])->name('register.teacher');
Route::post('/register/teacher', [AuthController::class, 'storeTeacher'])->name('register.teacher.submit');

Route::get('/register/parent', [AuthController::class, 'showParentRegister'])->name('register.parent');
Route::post('/register/parent', [AuthController::class, 'storeParent'])->name('register.parent.submit');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Mark-as-read is shared plumbing for both Teacher and Parent Notifications
// screens — ownership (recipient_user_id === current user) is checked
// inside the controller itself, so this only needs a real session, not a
// specific role.
Route::middleware('auth')->group(function () {
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

    // My Profile — Teacher + Parent share this exact same underlying
    // User data (first/last/middle_initial/contact_number/password), so
    // the mutating actions live here rather than duplicated per role;
    // the GET routes stay role-scoped below so each role's own styled
    // view is reached from its own dashboard nav.
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');
});

// Learner Login — Learner Actor Prompt Steps 1-2. Code entry only (typing
// the learnerCode, standing in for a QR scan); the avatar-tap variant for
// when a Parent is already logged in on the device is a later enhancement
// to this same screen, not built here. A separate 'learner' auth guard
// (config/auth.php) keeps this session independent of any Parent/Teacher/
// Admin session active in the same browser.
Route::get('/learner/login', [LearnerAuthController::class, 'showLogin'])->name('learner.login');
Route::post('/learner/login', [LearnerAuthController::class, 'login'])->name('learner.login.submit');

Route::middleware('learner.auth')->prefix('learner')->name('learner.')->group(function () {
    // Slice 4 — the first-login diagnostic. Deliberately NOT gated by
    // 'learner.diagnostic' — this is how a Learner completes it, so it
    // can't require itself to already be complete.
    Route::get('/diagnostic', [LearnerDiagnosticController::class, 'show'])->name('diagnostic.show');
    Route::get('/diagnostic/passage', [LearnerDiagnosticController::class, 'passage'])->name('diagnostic.passage');
    Route::post('/diagnostic/record', [LearnerDiagnosticController::class, 'submitRecording'])->name('diagnostic.record');

    Route::middleware('learner.diagnostic')->group(function () {
        Route::get('/dashboard', [LearnerAuthController::class, 'dashboard'])->name('dashboard');
        // Step 3 "Finding what to read" — resolves real Teacher assignments
        // (direct-to-Learner, then Class, then Class's Group tag — first
        // matching priority level wins). Repository-unlocked content (Parent
        // side) isn't built yet. Still stops short of the actual read-aloud/
        // AI-scoring UI (Sprint 4 Slices 2-3) with an honest note.
        Route::get('/activity/start', [LearnerAuthController::class, 'findActivity'])->name('activity.find');
        Route::get('/activity/{activity}', [LearnerAuthController::class, 'showActivity'])->name('activity.show');
        // Slice 2 — real audio capture + Reading-api integration. Ownership
        // is re-checked inside the controller via the same Activity model
        // method showActivity() uses, not duplicated.
        Route::post('/activity/{activity}/record', [LearnerReadingController::class, 'submitRecording'])->name('activity.record');
    });

    Route::post('/logout', [LearnerAuthController::class, 'logout'])->name('logout');
});

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');

// Admin Dashboard — 'auth' confirms a real session, 'admin' confirms
// user_type = Admin. A Teacher or Parent hitting these URLs directly
// gets a 403 from EnsureUserIsAdmin, not just a hidden nav link.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/teachers/{teacher}/activate', [AdminController::class, 'activateTeacher'])->name('teachers.activate');
    Route::post('/teachers/{teacher}/reject', [AdminController::class, 'rejectTeacher'])->name('teachers.reject');
    Route::post('/users/{user}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('users.toggle-status');
});

// Class Management — 'teacher' confirms user_type = Teacher (blocks Parent/
// Admin). The index route has NO 'teacher.active' guard: a Pending Teacher
// can still view this page (locked/explained), per the Admin Actor Prompt's
// "approval gates touching real students, not visibility" rule. Only the
// mutating 'store' route carries that guard — the actual security boundary.
Route::middleware(['auth', 'teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
    Route::post('/classes', [ClassController::class, 'store'])->middleware('teacher.active')->name('classes.store');
    Route::put('/classes/{class}', [ClassController::class, 'update'])->middleware('teacher.active')->name('classes.update');
    Route::post('/classes/{class}/join-learner', [ClassController::class, 'joinLearner'])->middleware('teacher.active')->name('classes.join-learner');

    // Activity Generation — Teacher Actor Prompt Step 7. Generate has NO
    // 'teacher.active' guard: a Pending Teacher can use their 2 free
    // credits (Step 3). My Activities' index is viewable while Pending too
    // (same pattern as Class Management), but its mutating actions
    // (approve/edit/reject) are Active-gated, since "everything else" is
    // locked while Pending per Step 3.
    Route::get('/activities/generate', [ActivityController::class, 'create'])->name('activities.create');
    Route::post('/activities/generate', [ActivityController::class, 'generate'])->name('activities.generate');
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities/{activity}/approve', [ActivityController::class, 'approve'])->middleware('teacher.active')->name('activities.approve');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->middleware('teacher.active')->name('activities.update');
    Route::post('/activities/{activity}/reject', [ActivityController::class, 'reject'])->middleware('teacher.active')->name('activities.reject');
    Route::post('/activities/{activity}/assign', [ActivityController::class, 'assign'])->middleware('teacher.active')->name('activities.assign');
    // Share to Repository — Teacher Actor Prompt Step 8's second required
    // action on an Approved card, same Active-gating as Assign.
    Route::post('/activities/{activity}/share', [ActivityController::class, 'shareToRepository'])->middleware('teacher.active')->name('activities.share');

    // Analytics — Teacher Actor Prompt Step 10. Read-only, so no
    // 'teacher.active' guard, same rule as Class Management's index.
    Route::get('/analytics', [AnalyticsController::class, 'teacherIndex'])->name('analytics.index');

    // Grade Promotions — Teacher Actor Prompt Step 9. Viewable while
    // Pending, same rule as Class Management's index; only the mutating
    // release/claim actions are Active-gated.
    Route::get('/promotions', [PromotionController::class, 'index'])->name('promotions.index');
    Route::post('/promotions/{learner}/release', [PromotionController::class, 'release'])->middleware('teacher.active')->name('promotions.release');
    Route::post('/promotions/{record}/claim', [PromotionController::class, 'claim'])->middleware('teacher.active')->name('promotions.claim');

    // Notifications — Teacher Actor Prompt Step 11.
    Route::get('/notifications', [NotificationController::class, 'teacherIndex'])->name('notifications.index');

    // My Profile — not spec'd in any actor prompt, a genuinely missing
    // feature built from reasonable judgment (see ProfileController).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // The real Teacher Dashboard — post-login landing for this role.
    // Read-only, viewable regardless of Active/Pending status (each linked
    // page handles its own status-gating internally).
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');
});

// Learner Account Creation — Parent Actor Prompt Steps 3-5. A Parent's own
// account has zero verification gate (unlike Teacher), so there's no
// active/pending split here — every mutating action here needs only 'parent'.
Route::middleware(['auth', 'parent'])->prefix('parent')->name('parent.')->group(function () {
    Route::get('/children', [LearnerController::class, 'index'])->name('children.index');
    Route::get('/children/create', [LearnerController::class, 'create'])->name('children.create');
    Route::post('/children', [LearnerController::class, 'store'])->name('children.store');
    Route::get('/children/link', [LearnerController::class, 'showLink'])->name('children.link');
    Route::post('/children/link', [LearnerController::class, 'storeLink'])->name('children.link.submit');

    // Progress — Parent Actor Prompt Step 7.
    Route::get('/progress', [AnalyticsController::class, 'parentIndex'])->name('progress');

    // Browse & Unlock the Open Repository — Parent Actor Prompt Step 6.
    Route::get('/repository', [RepositoryController::class, 'index'])->name('repository.index');
    Route::post('/repository/{listing}/unlock', [RepositoryController::class, 'unlock'])->name('repository.unlock');
    Route::post('/repository/{listing}/rate', [RepositoryController::class, 'rate'])->name('repository.rate');

    // Notifications — Parent Actor Prompt Step 8.
    Route::get('/notifications', [NotificationController::class, 'parentIndex'])->name('notifications.index');

    // My Profile — not spec'd in any actor prompt, a genuinely missing
    // feature built from reasonable judgment (see ProfileController).
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // The real Parent Dashboard — post-login landing for this role.
    Route::get('/dashboard', [ParentDashboardController::class, 'index'])->name('dashboard');
});

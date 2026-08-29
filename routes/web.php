<?php

use App\Http\Controllers\AuthController;
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

Route::get('/dashboard', [AuthController::class, 'dashboardPlaceholder'])
    ->middleware('auth')
    ->name('dashboard.placeholder');

// Learner PIN login isn't built yet (a later slice, different mechanism
// entirely — no password, avatar + PIN based). Clear placeholder for now
// so the landing page's Learner tile doesn't 404.
Route::get('/learner/login', function () {
    return 'Learner PIN login coming in a later slice.';
})->name('learner.login.placeholder');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');

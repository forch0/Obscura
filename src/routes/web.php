<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\KeypairController;
use App\Http\Controllers\Auth\RecoveryCodeController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('recover', [RecoveryCodeController::class, 'showRecoverForm'])->name('recover');
    Route::post('recover', [RecoveryCodeController::class, 'recover']);
    Route::post('recover/reset', [RecoveryCodeController::class, 'resetPassword'])->name('recover.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/keygen', fn () => view('auth.keygen'))->name('keygen');
    Route::post('/api/keypair', [KeypairController::class, 'store'])->name('keypair.store');
    Route::get('/recovery-code', [RecoveryCodeController::class, 'show'])->name('recovery-code');

    // Email verification
    Route::get('/email/verify', [EmailVerificationController::class, 'showNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.send');
});

Route::middleware(['auth', 'keypair'])->group(function () {
    Route::resource('workspaces', WorkspaceController::class);
});

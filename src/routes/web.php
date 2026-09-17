<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\ArchitectureController;
use App\Http\Controllers\UseCasesController;
use App\Http\Controllers\AccessCodeController;
use App\Http\Controllers\AccessCodeEntryController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\KeypairController;
use App\Http\Controllers\Auth\RecoveryCodeController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GalleryMemberController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RekeyController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('workspaces.index')
        : redirect()->route('home');
});

Route::get('/home', function () {
    return view('welcome');
})->name('home');

Route::get('/about', AboutController::class)->name('about');
Route::get('/use-cases', UseCasesController::class)->name('use-cases');
Route::get('/architecture', ArchitectureController::class)->name('architecture');
Route::get('/showcase', fn () => view('showcase'))->name('showcase');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('recover', [RecoveryCodeController::class, 'showRecoverForm'])->name('recover');
    Route::post('recover', [RecoveryCodeController::class, 'recover'])->middleware('throttle:recover');
    Route::post('recover/reset', [RecoveryCodeController::class, 'resetPassword'])->name('recover.reset')->middleware('throttle:recover');

    // Invitee: enter access code
    Route::get('enter', [AccessCodeEntryController::class, 'create'])->name('enter');
    Route::post('enter', [AccessCodeEntryController::class, 'store'])->middleware('throttle:code-entry');

    // Guest access-code viewer (requires valid code session cookie)
    Route::middleware(['access_code:required', 'throttle:access-view'])->group(function () {
        Route::get('access', [App\Http\Controllers\AccessViewController::class, 'show'])->name('access.view');
        Route::get('access/galleries/{gallery}/media', [App\Http\Controllers\AccessViewController::class, 'media'])->name('access.media.index');
        Route::get('access/media/{medium}/blob', [App\Http\Controllers\AccessViewController::class, 'blob'])->name('access.media.blob');
        Route::get('access/media/{medium}/thumbnail', [App\Http\Controllers\AccessViewController::class, 'thumbnail'])->name('access.media.thumbnail');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/keygen', fn () => view('auth.keygen'))->name('keygen');
    Route::post('/api/keypair', [KeypairController::class, 'store'])->name('keypair.store')->middleware('throttle:keypair');
    Route::get('/api/keypair', [KeypairController::class, 'show'])->name('keypair.show')->middleware('throttle:keypair');
    Route::get('/recovery-code', [RecoveryCodeController::class, 'show'])->name('recovery-code');

    // Email verification
    Route::get('/email/verify', [EmailVerificationController::class, 'showNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])->name('verification.send')->middleware('throttle:email');
});

Route::middleware(['auth', 'keypair'])->group(function () {
    Route::resource('workspaces', WorkspaceController::class)
        ->middlewareFor('store', 'throttle:writes')
        ->middlewareFor(['update', 'destroy'], 'throttle:writes');

    // Workspace members (owner-only, scoped under workspace)
    Route::prefix('workspaces/{workspace}')->group(function () {
        Route::get('members', [WorkspaceMemberController::class, 'index'])->name('workspaces.members');
        Route::post('members', [WorkspaceMemberController::class, 'store'])->name('workspaces.members.store')->middleware('throttle:writes');
        Route::post('members/lookup', [WorkspaceMemberController::class, 'lookup'])->name('workspaces.members.lookup')->middleware('throttle:writes');
        Route::put('members/{member}', [WorkspaceMemberController::class, 'update'])->name('workspaces.members.update')->middleware('throttle:writes');
        Route::delete('members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy')->middleware('throttle:writes');
    });

    // Access codes (owner-only, scoped under workspace)
    Route::prefix('workspaces/{workspace}')->group(function () {
        Route::get('access-codes', [AccessCodeController::class, 'index'])->name('access-codes.index');
        Route::get('access-codes/create', [AccessCodeController::class, 'create'])->name('access-codes.create');
        Route::post('access-codes', [AccessCodeController::class, 'store'])->name('access-codes.store')->middleware('throttle:code-generate');
        Route::get('access-codes/{code}', [AccessCodeController::class, 'show'])->name('access-codes.show');
        Route::post('access-codes/{code}/dek', [AccessCodeController::class, 'storeWrappedDek'])->name('access-codes.dek')->middleware('throttle:code-generate');
        Route::delete('access-codes/{code}', [AccessCodeController::class, 'revoke'])->name('access-codes.revoke')->middleware('throttle:writes');
    });

    // Collections (nested under workspaces)
    Route::prefix('workspaces/{workspace}')->group(function () {
        Route::get('collections', [CollectionController::class, 'index'])->name('collections.index');
        Route::get('collections/create', [CollectionController::class, 'create'])->name('collections.create');
        Route::post('collections', [CollectionController::class, 'store'])->name('collections.store')->middleware('throttle:writes');
        Route::get('collections/{collection}', [CollectionController::class, 'show'])->name('collections.show');
        Route::get('collections/{collection}/edit', [CollectionController::class, 'edit'])->name('collections.edit');
        Route::put('collections/{collection}', [CollectionController::class, 'update'])->name('collections.update')->middleware('throttle:writes');
        Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy')->middleware('throttle:writes');
    });

    // Galleries (nested under collections)
    Route::prefix('collections/{collection}')->group(function () {
        Route::get('galleries/create', [GalleryController::class, 'create'])->name('galleries.create');
        Route::post('galleries', [GalleryController::class, 'store'])->name('galleries.store')->middleware('throttle:writes');
        Route::get('galleries/{gallery}', [GalleryController::class, 'show'])->name('galleries.show');
        Route::get('galleries/{gallery}/edit', [GalleryController::class, 'edit'])->name('galleries.edit');
        Route::put('galleries/{gallery}', [GalleryController::class, 'update'])->name('galleries.update')->middleware('throttle:writes');
        Route::delete('galleries/{gallery}', [GalleryController::class, 'destroy'])->name('galleries.destroy')->middleware('throttle:writes');

        // Gallery members (shared/joint only)
        Route::get('galleries/{gallery}/members', [GalleryMemberController::class, 'index'])->name('galleries.members');
        Route::post('galleries/{gallery}/members', [GalleryMemberController::class, 'store'])->name('galleries.members.store')->middleware('throttle:writes');
        Route::put('galleries/{gallery}/members/{member}', [GalleryMemberController::class, 'update'])->name('galleries.members.update')->middleware('throttle:writes');
        Route::delete('galleries/{gallery}/members/{member}', [GalleryMemberController::class, 'destroy'])->name('galleries.members.destroy')->middleware('throttle:writes');
    });

    // Media (nested under galleries for create/list; direct for blob/update/delete)
    Route::post('galleries/{gallery}/media', [MediaController::class, 'store'])->name('media.store')->middleware('throttle:media-upload');
    Route::get('galleries/{gallery}/media', [MediaController::class, 'index'])->name('media.index')->middleware('throttle:media-read');
    Route::get('media/{medium}', [MediaController::class, 'show'])->name('media.show');
    Route::put('media/{medium}', [MediaController::class, 'update'])->name('media.update')->middleware('throttle:writes');
    Route::delete('media/{medium}', [MediaController::class, 'destroy'])->name('media.destroy')->middleware('throttle:writes');
    Route::get('media/{medium}/blob', [MediaController::class, 'blob'])->name('media.blob')->middleware('throttle:media-read');
    Route::get('media/{medium}/thumbnail', [MediaController::class, 'thumbnail'])->name('media.thumbnail')->middleware('throttle:media-read');

    // Re-key (owner only)
    Route::get('workspaces/{workspace}/rekey', [RekeyController::class, 'show'])->name('workspaces.rekey');
    Route::post('workspaces/{workspace}/rekey', [RekeyController::class, 'initiate'])->name('workspaces.rekey.initiate')->middleware('throttle:rekey');
    Route::get('workspaces/{workspace}/rekey/status', [RekeyController::class, 'status'])->name('workspaces.rekey.status')->middleware('throttle:rekey');
    Route::post('workspaces/{workspace}/rekey/{job}/complete', [RekeyController::class, 'complete'])->name('workspaces.rekey.complete')->middleware('throttle:rekey');
});

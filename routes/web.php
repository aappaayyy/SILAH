<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\RegistrationAdminController;
use App\Http\Controllers\Portal\AccountCheckController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\RegistrationController;
use App\Http\Controllers\Portal\ResetController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

Route::get('/', [PortalController::class, 'index'])->name('home');

Route::post('/cek-akun', [AccountCheckController::class, 'check'])
    ->middleware(['throttle:portal-check', ProtectAgainstSpam::class])
    ->name('check');

Route::middleware('throttle:portal')->group(function () {
    Route::post('/reset', [ResetController::class, 'store'])->name('reset.store');
    Route::post('/batal', [PortalController::class, 'cancel'])->name('portal.cancel');
    Route::post('/daftar', [RegistrationController::class, 'store'])
        ->middleware(ProtectAgainstSpam::class)
        ->name('register.store');   // dibuat di langkah pendaftaran
    Route::post('/ajukan-ulang', [PortalController::class, 'reapply'])->name('portal.reapply');
});

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'create'])->name('login');
        Route::post('login', [AuthController::class, 'store'])
            ->middleware('throttle:admin-login')->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('pendaftaran', [RegistrationAdminController::class, 'index'])->name('registrations.index');
        Route::get('pendaftaran/{registration}', [RegistrationAdminController::class, 'show'])->name('registrations.show');
        Route::get('pendaftaran/{registration}/dokumen', [RegistrationAdminController::class, 'document'])->name('registrations.document');
        Route::post('pendaftaran/{registration}/setujui', [RegistrationAdminController::class, 'approve'])->name('registrations.approve');
        Route::post('pendaftaran/{registration}/tolak', [RegistrationAdminController::class, 'reject'])->name('registrations.reject');
    });
});
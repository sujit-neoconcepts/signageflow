<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TwoFAController;

Route::prefix('admin')->middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
                ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
                ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
    ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
                ->name('password.store');


});

Route::prefix('admin')->middleware('auth')->controller(TwoFAController::class)->group(function () {
    Route::get('two-factor-authentication', 'index')->name('2fa.index');
    Route::post('two-factor-authentication/store', 'store')->name('2fa.store');
    Route::get('two-factor-authentication/resend', 'resend')->name('2fa.resend');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
    Route::get('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout.get');
});

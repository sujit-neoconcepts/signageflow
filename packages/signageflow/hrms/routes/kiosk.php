<?php

use Illuminate\Support\Facades\Route;
use SignageFlow\Hrms\Http\Controllers\Web\Attendance\KioskTerminalController;

Route::get('/', [KioskTerminalController::class, 'show'])->name('terminal');
Route::post('/punch', [KioskTerminalController::class, 'punch'])->name('punch');
Route::post('/biometric-webhook', [KioskTerminalController::class, 'biometricWebhook'])->name('biometricWebhook');

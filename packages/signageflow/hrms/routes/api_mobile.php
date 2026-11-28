<?php

use Illuminate\Support\Facades\Route;
use SignageFlow\Hrms\Http\Controllers\Mobile\AttendanceMobileController;
use SignageFlow\Hrms\Http\Controllers\Mobile\LeaveMobileController;
use SignageFlow\Hrms\Http\Controllers\Mobile\PayslipMobileController;

Route::prefix('attendance')->name('attendance.')->group(function () {
    Route::get('today', [AttendanceMobileController::class, 'today'])->name('today');
    Route::post('punch', [AttendanceMobileController::class, 'punch'])->name('punch');
    Route::get('history', [AttendanceMobileController::class, 'history'])->name('history');
    Route::get('requests', [AttendanceMobileController::class, 'requests'])->name('requests');
    Route::post('regularize', [AttendanceMobileController::class, 'regularize'])->name('regularize');
});

Route::prefix('leave')->name('leave.')->group(function () {
    Route::get('summary', [LeaveMobileController::class, 'summary'])->name('summary');
    Route::get('requests', [LeaveMobileController::class, 'requests'])->name('requests');
    Route::post('requests', [LeaveMobileController::class, 'store'])->name('store');
    Route::post('requests/{leaveRequest}/cancel', [LeaveMobileController::class, 'cancel'])->name('cancel');
});

Route::post('fwa/requests', [LeaveMobileController::class, 'storeFwa'])->name('fwa.store');

Route::prefix('payslips')->name('payslips.')->group(function () {
    Route::get('/', [PayslipMobileController::class, 'index'])->name('index');
    Route::get('{payRunItem}/download', [PayslipMobileController::class, 'download'])->name('download');
});

<?php

use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\ExpenseController as MobileExpenseController;
use App\Http\Controllers\Api\Mobile\NotificationController as MobileNotificationController;
use App\Http\Controllers\Api\Mobile\TaskController as MobileTaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('mobile')->name('mobile.')->group(function () {
    Route::post('login', [MobileAuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [MobileAuthController::class, 'logout']);
        Route::get('tasks/meta', [MobileTaskController::class, 'meta'])->name('tasks.meta');
        Route::get('tasks', [MobileTaskController::class, 'index'])->name('tasks.index');
        Route::get('tasks/{task}', [MobileTaskController::class, 'show'])->name('tasks.show');
        Route::post('tasks/{task}/status', [MobileTaskController::class, 'updateStatus'])->name('tasks.status');
        Route::post('tasks/{task}/comments', [MobileTaskController::class, 'addComment'])->name('tasks.comments');
        Route::post('tasks/{task}/expenses', [MobileTaskController::class, 'storeExpense'])->name('tasks.expenses');
        Route::get('tasks/files/{taskFile}', [MobileTaskController::class, 'downloadTaskFile'])->name('tasks.file.download');
        Route::get('tasks/comment-files/{commentFile}', [MobileTaskController::class, 'downloadCommentFile'])->name('tasks.comment-file.download');
        Route::get('tasks/job-files/{jobFile}', [MobileTaskController::class, 'downloadJobFile'])->name('tasks.job-file.download');
        Route::get('expenses/meta', [MobileExpenseController::class, 'meta'])->name('expenses.meta');
        Route::get('expenses', [MobileExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [MobileExpenseController::class, 'store'])->name('expenses.store');
        Route::post('fcm-token', [MobileExpenseController::class, 'storeFcmToken'])->name('fcm-token.store');

        Route::get('notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{id}/read', [MobileNotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('notifications/read-all', [MobileNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    });
});

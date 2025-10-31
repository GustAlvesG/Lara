<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\MemberAuthController;
use App\Http\Controllers\Auth\LoginTokenController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\ScheduleController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::get('/souburro', [MemberAuthController::class, 'souburro']);

Route::middleware('api_token')->group(function () {

    Route::post('/login', [MemberAuthController::class, 'login']);
    Route::post('/register', [MemberAuthController::class, 'register']);

    Route::middleware('login_token')->group(function () {
        Route::get('/verify-token', [LoginTokenController::class, 'validate']);

        Route::post('/change-password', [MemberAuthController::class, 'changePassword']);

        //Routes Group places
        Route::prefix('places')->group(function () {
            Route::prefix('group')->group(function () {
                Route::get('/{category}', [PlaceGroupController::class, 'indexByCategory']);
                Route::get('/rules/{id}', [PlaceGroupController::class, 'scheduleRules']);
            });
            Route::get('/{id}', [PlaceController::class, 'indexByGroup']);
        });

        //Routes Group places
        Route::prefix('place')->group(function () {
            Route::get('/{id}', [PlaceController::class, 'show']);
        });

        Route::prefix('schedule')->group(function () {

            Route::get('/', [ScheduleController::class, 'index'])->name('api.schedule.index');
            Route::post('/', [ScheduleController::class, 'store']);
            Route::get('/{id}', [ScheduleController::class, 'show']);
            Route::put('/{id}/update', [ScheduleController::class, 'update']);
            Route::get('/place/{place_id}/', [ScheduleController::class, 'indexByPlace']);
            Route::get('/member/{member_id}/', [ScheduleController::class, 'indexByMember']);
            Route::put('/update-status', [ScheduleController::class, 'updateStatus']);
            Route::delete('/delete-pending', [ScheduleController::class, 'destroyPending']);
        });

    });
});
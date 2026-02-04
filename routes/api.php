<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\MemberAuthController;
use App\Http\Controllers\Auth\LoginTokenController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleRulesController;
use App\Http\Controllers\SchedulePaymentController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Company\CompanyAccessRulesController;
use App\Http\Controllers\EmailController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::get('/souburro', [MemberAuthController::class, 'souburro']);


Route::get('/schedule/generate-pdf', [ScheduleController::class, 'generateDailySchedulePDF'])->name('schedule.generatePDF');

Route::prefix('whatsapp')->group(function () {
    Route::get('/webhook', [WhatsAppController::class, 'verifyWebhook']);
    Route::post('/webhook', [WhatsAppController::class, 'handleWebhook']);
    Route::post('/send-message', [WhatsAppController::class, 'sendMessage']);
});

Route::prefix('company-access')->group(function () {
    Route::post('/validate-access', [CompanyAccessRulesController::class, 'validateCompanyAccess'])->name('company_access.validate');
});

Route::middleware('api_token')->group(function () {
    Route::get('/image/{member_id}', [MemberAuthController::class, 'getImage'])->name('member.getImage');
    Route::post('/login', [MemberAuthController::class, 'login']);
    Route::post('/register', [MemberAuthController::class, 'register']);
    Route::post('/check-member', [MemberAuthController::class, 'checkMember']);
    Route::put('/change-password', [MemberAuthController::class, 'changePassword']);

    Route::post('send-email', [EmailController::class, 'submit']);

    Route::middleware('login_token')->group(function () {
        Route::get('/verify-token', [LoginTokenController::class, 'validate']);

        Route::prefix('/member')->group(function () {
            Route::post('/by-title', [MemberController::class, 'getByTitle'])->name('member.getByTitle')->withoutMiddleware(['login_token']);
            Route::put('/update', [MemberAuthController::class, 'update']);
        });

        //Routes Group places
        Route::prefix('places')->group(function () {
            Route::prefix('group')->group(function () {
                Route::get('/', [PlaceGroupController::class, 'index_api']);
                Route::get('/{category}', [PlaceGroupController::class, 'indexByCategory']);
                Route::get('/rules/{id}', [PlaceGroupController::class, 'scheduleRules']);
            });
            Route::post('/', [PlaceController::class, 'indexByGroup']);
        });

        //Routes Group places
        Route::prefix('place')->group(function () {
            Route::get('/{id}', [PlaceController::class, 'show']);
        });

        Route::prefix('schedule')->group(function () {

            Route::get('/', [ScheduleController::class, 'index_api'])->name('api.schedule.index');
            Route::post('/', [ScheduleController::class, 'store'])->name('api.schedule.store')->withoutMiddleware(['login_token']);

            Route::put('/{id}/update', [ScheduleController::class, 'update']);
            Route::post('/place', [ScheduleController::class, 'indexByPlace']); // False POST, this is a GET REQUEST
            Route::get('/member/{member_id}/', [ScheduleController::class, 'indexByMember']);
            Route::put('/update-status', [ScheduleController::class, 'updateStatus']);
            Route::post('/payment', [SchedulePaymentController::class, 'store']);
            Route::delete('/delete-pending', [ScheduleController::class, 'destroyPending']);


            Route::post('/time-options', [ScheduleRulesController::class, 'getTimeOptions'])->name('api.schedule.getTimeOptions')->withoutMiddleware(['login_token']);
        });
    });
});

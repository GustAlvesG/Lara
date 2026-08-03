<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\MemberAuthController;
use App\Http\Controllers\Auth\LoginTokenController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleRulesController;
use App\Http\Controllers\SchedulePaymentController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Company\CompanyAccessRulesController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\TelegramContactController;
use App\Http\Controllers\FreelancerController;
use App\Http\Controllers\FunctionFreelancerController;
use App\Http\Controllers\FreelancerServiceController;
use App\Http\Controllers\ParkingAuthorizationController;
use App\Http\Controllers\UberAccessRequestWebhookController;
use App\Http\Controllers\InformationSearchController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::get('/test', [TestController::class, 'index'])->name('api.test');


Route::get('/schedule/generate-pdf', [ScheduleController::class, 'generateDailySchedulePDF'])->name('schedule.generatePDF');

Route::prefix('whatsapp')->group(function () {
    Route::get('/webhook', [WhatsAppController::class, 'verifyWebhook']);
    Route::post('/webhook', [WhatsAppController::class, 'handleWebhook']);
    Route::post('/send-message', [WhatsAppController::class, 'sendMessage']);
});

Route::prefix('company-access')->group(function () {
    Route::post('/validate-access', [CompanyAccessRulesController::class, 'validateCompanyAccess'])->name('company_access.validate');
    Route::post('/register-access', [CompanyAccessRulesController::class, 'registerAccess'])->name('company_access.register');
    Route::post('/register-worker-access', [CompanyAccessRulesController::class, 'registerWorkerAccess'])->name('company_access.register_worker');
});

Route::middleware('api_token')->group(function () {

    Route::post('/parking/check', [ParkingAuthorizationController::class, 'checkPlateFromCamera'])
        ->name('api.parking.check.post');

    Route::get('/parking/check/{plate}', [ParkingAuthorizationController::class, 'checkPlate'])
        ->name('api.parking.check');

    Route::get('/parking/authorized', [ParkingAuthorizationController::class, 'listAuthorized'])
        ->name('api.parking.authorized');

    Route::prefix('information')->group(function () {
        //Busca por palavra-chave em name, description e category do data_info
        Route::get('/search', [InformationSearchController::class, 'index'])
            ->name('api.information.search');
    });

    Route::prefix('webhooks')->group(function () {
        Route::post('/whatsapp', [UberAccessRequestWebhookController::class, 'handle'])
            ->name('api.webhooks.whatsapp');
    });

    Route::prefix('telegram')->group(function () {
        Route::post('/get-contacts', [TelegramContactController::class, 'find'])->name('telegram.findContacts');
        Route::post('/contacts', [TelegramContactController::class, 'store'])->name('telegram.createContact');
        Route::put('/contacts/{id}', [TelegramContactController::class, 'update'])->name('telegram.updateContact');

        //Login do usuário do sistema por matrícula (restrito ao papel comercial)
        Route::post('/user/login', [UserAuthController::class, 'login'])->name('telegram.userLogin');

        Route::prefix('freelancer')->group(function () {
            //Consulta freelancer por CPF (404 quando não existe -> cadastrar)
            Route::get('/freelancer/{cpf}', [FreelancerController::class, 'show']);
            //Cadastra freelancer
            Route::post('/freelancer', [FreelancerController::class, 'store']);
            //Lista funções disponíveis
            Route::get('/functions', [FunctionFreelancerController::class, 'index']);

            //Serviços/contratos do freelancer
            Route::get('/freelancer/{cpf}/services', [FreelancerServiceController::class, 'indexByCpf']);
            Route::post('/service', [FreelancerServiceController::class, 'store']);
            Route::put('/service/{freelancerService}', [FreelancerServiceController::class, 'update']);
            //Assinatura do freelancer
            Route::post('/service/{freelancerService}/sign', [FreelancerServiceController::class, 'sign']);
        });
    });
    Route::post('/login', [MemberAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [MemberAuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/check-member', [MemberAuthController::class, 'checkMember'])->middleware('throttle:10,1');
    Route::put('/change-password', [MemberAuthController::class, 'changePassword'])->middleware('throttle:5,1');

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

            Route::get('/', [PlaceGroupController::class, 'index_api'])->name('api.schedule.index');
            Route::post('/', [ScheduleController::class, 'store'])->name('api.schedule.store')->withoutMiddleware(['login_token']);

            Route::put('/{id}/update', [ScheduleController::class, 'update']);
            Route::post('/place', [ScheduleController::class, 'indexByPlace']); // False POST, this is a GET REQUEST
            Route::get('/member/{member_id}/', [ScheduleController::class, 'indexByMember']);
            Route::put('/update-status', [ScheduleController::class, 'updateStatus']);
            Route::post('/payment', [SchedulePaymentController::class, 'store']);
            Route::delete('/delete-pending', [ScheduleController::class, 'destroyPending']);

            Route::get('/home-assistant/automation', [ScheduleController::class, 'homeAssistantAutomation'])->name('api.schedule.homeAssistantAutomation')->withoutMiddleware(['login_token']);



            Route::post('/time-options', [ScheduleRulesController::class, 'getTimeOptions'])->name('api.schedule.getTimeOptions')->withoutMiddleware(['login_token']);
        });
    });
});

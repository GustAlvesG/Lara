<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Support\Placar\PlacarAbilities;
use App\Http\Controllers\Placar\Api\ModalidadeController as PlacarModalidadeController;
use App\Http\Controllers\Placar\Api\EquipeController as PlacarEquipeController;
use App\Http\Controllers\Placar\Api\TimeController as PlacarTimeController;
use App\Http\Controllers\Placar\Api\JogoController as PlacarJogoController;
use App\Http\Controllers\Placar\Api\JogadorController as PlacarJogadorController;
use App\Http\Controllers\Placar\Api\EscalacaoController as PlacarEscalacaoController;
use App\Http\Controllers\Placar\Api\JogoEventoController as PlacarJogoEventoController;
use App\Http\Controllers\Placar\Api\ScoutController as PlacarScoutController;
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

/*
|--------------------------------------------------------------------------
| Placar Clube — API para o Node (placar eletrônico ao vivo)
|--------------------------------------------------------------------------
|
| Autenticação própria (Sanctum, token de acesso pessoal do ApiCliente),
| NÃO a sessão/CSRF web. Rate limit generoso porque o Node manda eventos em
| lote, não um a um. Token: `php artisan placar:token {nome}`.
|
| Os endpoints de escrita (ciclo de vida do jogo, eventos, criação em
| campo) e de scout agregado entram nas etapas seguintes. O /ping é só
| diagnóstico, para validar a autenticação sem depender de dado nenhum.
*/
Route::prefix('placar')
    ->middleware(['auth:sanctum', 'abilities:' . PlacarAbilities::OPERAR, 'throttle:300,1'])
    ->group(function () {
        Route::get('/ping', function (Request $request) {
            return response()->json([
                'ok' => true,
                'cliente' => $request->user()?->nome,
            ]);
        })->name('api.placar.ping');

        // Leitura — consumo e seleção pelo Node (modo planejado).
        Route::get('/modalidades', [PlacarModalidadeController::class, 'index'])->name('api.placar.modalidades.index');

        Route::get('/equipes', [PlacarEquipeController::class, 'index'])->name('api.placar.equipes.index');
        Route::post('/equipes/{equipe}/logo', [PlacarEquipeController::class, 'storeLogo'])->name('api.placar.equipes.logo.store');
        Route::delete('/equipes/{equipe}/logo', [PlacarEquipeController::class, 'destroyLogo'])->name('api.placar.equipes.logo.destroy');

        Route::get('/times', [PlacarTimeController::class, 'index'])->name('api.placar.times.index');
        Route::get('/times/{time}/elenco', [PlacarTimeController::class, 'elenco'])->name('api.placar.times.elenco');
        Route::post('/times/{time}/logo', [PlacarTimeController::class, 'storeLogo'])->name('api.placar.times.logo.store');
        Route::delete('/times/{time}/logo', [PlacarTimeController::class, 'destroyLogo'])->name('api.placar.times.logo.destroy');

        Route::post('/jogadores/{jogador}/foto', [PlacarJogadorController::class, 'storeFoto'])->name('api.placar.jogadores.foto.store');
        Route::delete('/jogadores/{jogador}/foto', [PlacarJogadorController::class, 'destroyFoto'])->name('api.placar.jogadores.foto.destroy');
        Route::post('/jogadores/{jogador}/video', [PlacarJogadorController::class, 'storeVideo'])->name('api.placar.jogadores.video.store');
        Route::delete('/jogadores/{jogador}/video', [PlacarJogadorController::class, 'destroyVideo'])->name('api.placar.jogadores.video.destroy');

        Route::get('/jogos', [PlacarJogoController::class, 'index'])->name('api.placar.jogos.index');
        Route::get('/jogos/{jogo}', [PlacarJogoController::class, 'show'])->name('api.placar.jogos.show');
        // Estado de quadra ao vivo: em quadra/banco, tempos técnicos e
        // substituições restantes no período.
        Route::get('/jogos/{jogo}/situacao', [PlacarJogoController::class, 'situacao'])->name('api.placar.jogos.situacao');

        // Escrita — criação em campo (modo avulso): jogo não planejado,
        // cadastro completo em até quatro chamadas. Sempre criado_em_campo = true.
        Route::post('/equipes', [PlacarEquipeController::class, 'store'])->name('api.placar.equipes.store');
        Route::post('/times', [PlacarTimeController::class, 'store'])->name('api.placar.times.store');
        Route::post('/jogadores', [PlacarJogadorController::class, 'store'])->name('api.placar.jogadores.store');
        Route::post('/jogos', [PlacarJogoController::class, 'store'])->name('api.placar.jogos.store');

        // Ciclo de vida do jogo + o endpoint mais importante da API (eventos).
        Route::post('/jogos/{jogo}/iniciar', [PlacarJogoController::class, 'iniciar'])->name('api.placar.jogos.iniciar');
        Route::post('/jogos/{jogo}/escalacao', [PlacarEscalacaoController::class, 'store'])->name('api.placar.jogos.escalacao');
        Route::post('/jogos/{jogo}/eventos', [PlacarJogoEventoController::class, 'store'])->name('api.placar.jogos.eventos');
        Route::post('/jogos/{jogo}/encerrar', [PlacarJogoController::class, 'encerrar'])->name('api.placar.jogos.encerrar');

        // Scout — leitura de jogo_eventos, sempre com a partida como unidade:
        // súmula do jogo, ficha minutada de um jogador nele, e as partidas
        // em que um jogador atuou. Não há ranking de artilharia.
        Route::get('/jogos/{jogo}/sumula', [PlacarScoutController::class, 'sumula'])->name('api.placar.jogos.sumula');
        Route::get('/jogos/{jogo}/jogadores/{jogador}/atuacao', [PlacarScoutController::class, 'atuacao'])->name('api.placar.jogos.atuacao');
        Route::get('/scout/jogadores/{jogador}', [PlacarScoutController::class, 'jogador'])->name('api.placar.scout.jogador');
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
    Route::post('/register-freelancer-access', [CompanyAccessRulesController::class, 'registerFreelancerAccess'])->name('company_access.register_freelancer');
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

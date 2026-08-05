<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Company\CompanyWorkerController as WorkerController;
use App\Http\Controllers\Company\CompanyAccessRulesController as CompanyRulesController;
use App\Http\Controllers\Tournament\TournamentController;

use App\Http\Controllers\VideoWallController;
use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchedulePaymentController;
use App\Http\Controllers\ScheduleRulesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\CompTimeController;
use App\Http\Controllers\ParkingAuthorizationController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\EmailController;

use App\Http\Controllers\AvisoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Freelancer\FinanceController as FreelancerFinanceController;
use App\Http\Controllers\Freelancer\FreelancerController as FreelancerWebController;
use App\Http\Controllers\Freelancer\FunctionController as FreelancerFunctionController;
use App\Http\Controllers\Freelancer\BatchController as FreelancerBatchController;
use App\Http\Controllers\Freelancer\ServiceController as FreelancerServiceWebController;
use App\Http\Controllers\Freelancer\KioskController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaraChatController;
use App\Http\Controllers\HomeAssistantController;
use App\Http\Controllers\CardIssuerController;
use App\Http\Controllers\CardTemplateController;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

// Kiosk de assinatura (tablet) — AUTENTICAÇÃO PRÓPRIA, fora da sessão web.
// Entra-se com matrícula + PIN; a própria sessão de kiosk (operator_id + mode)
// protege os endpoints. Atendem freelancers os usuários com `manage freelancers`;
// assinam como contraparte os coordenadores do setor Comercial.
Route::prefix('kiosk')->group(function () {
    Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
    Route::get('/session', [KioskController::class, 'session'])->name('kiosk.session');
    Route::post('/login', [KioskController::class, 'login'])->middleware('throttle:10,1')->name('kiosk.login');
    Route::post('/mode', [KioskController::class, 'mode'])->name('kiosk.mode');
    Route::post('/logout', [KioskController::class, 'logout'])->name('kiosk.logout');
    Route::get('/functions', [KioskController::class, 'functions'])->name('kiosk.functions');
    Route::get('/freelancer/{cpf}', [KioskController::class, 'findFreelancer'])
        ->where('cpf', '[0-9]{11}')->name('kiosk.freelancer.find');
    Route::post('/freelancer', [KioskController::class, 'storeFreelancer'])->name('kiosk.freelancer.store');
    // Completar o cadastro no tablet destrava a geração do contrato.
    Route::put('/freelancer/{freelancer}', [KioskController::class, 'updateFreelancer'])->name('kiosk.freelancer.update');
    Route::get('/freelancer/{freelancer}/services', [KioskController::class, 'services'])->name('kiosk.freelancer.services');
    Route::post('/service', [KioskController::class, 'storeService'])
        ->middleware('throttle:20,1')->name('kiosk.service.store');
    // Código de liberação do limite semanal por e-mail. Throttle baixo: é um
    // e-mail para uma caixa de terceiro, não um endpoint de consulta.
    Route::post('/service/weekly-limit-code', [KioskController::class, 'weeklyLimitCode'])
        ->middleware('throttle:6,1')->name('kiosk.service.weekly-limit-code');
    // Aditivo: o turno mudou depois da assinatura. Gera um contrato novo preso
    // ao base, que passa a ser o documento válido daquele turno.
    Route::post('/service/{freelancerService}/amendment', [KioskController::class, 'storeAmendment'])
        ->middleware('throttle:20,1')->name('kiosk.service.amendment');
    // Comissão de venda: o outro tipo de aditivo, assinado ao final do
    // expediente e exclusivo das funções que a recebem (hoje, Garçom).
    Route::post('/service/{freelancerService}/commission', [KioskController::class, 'storeCommission'])
        ->middleware('throttle:20,1')->name('kiosk.service.commission');
    // Apuração das vendas no MultiVendas, antes de gerar a comissão. Throttle
    // apertado: cada toque é uma consulta pesada num banco de terceiro.
    Route::post('/service/{freelancerService}/sales-report', [KioskController::class, 'salesReport'])
        ->middleware('throttle:20,1')->name('kiosk.service.sales-report');
    Route::post('/service/{freelancerService}/sign', [KioskController::class, 'signService'])
        ->middleware('throttle:20,1')->name('kiosk.service.sign');

    // Imagem da assinatura — servida por rota (e não pelo disco público) porque é
    // dado pessoal e porque o link public/storage nem sempre existe no ambiente.
    Route::get('/service/{freelancerService}/signature/{party}', [KioskController::class, 'signatureImage'])
        ->where('party', 'freelancer|coordinator')->name('kiosk.service.signature');

    // Fila do coordenador: contratos assinados pelo freelancer aguardando a contraparte.
    Route::get('/coordinator/services', [KioskController::class, 'coordinatorServices'])
        ->name('kiosk.coordinator.services');

    // Lote de aprovação: o coordenador monta e envia pelo tablet. A análise da
    // gerência, por decisão de processo, só existe na web.
    Route::get('/coordinator/batch', [KioskController::class, 'batch'])->name('kiosk.batch.show');
    Route::post('/coordinator/batch/items', [KioskController::class, 'addToBatch'])->name('kiosk.batch.items.add');
    Route::delete('/coordinator/batch/items/{freelancerService}', [KioskController::class, 'removeFromBatch'])
        ->name('kiosk.batch.items.remove');
    Route::post('/coordinator/batch/send', [KioskController::class, 'sendBatch'])
        ->middleware('throttle:20,1')->name('kiosk.batch.send');
    Route::post('/service/{freelancerService}/sign-coordinator', [KioskController::class, 'signServiceAsCoordinator'])
        ->middleware('throttle:40,1')->name('kiosk.service.sign-coordinator');
});

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/pin', [ProfileController::class, 'updatePin'])->name('profile.pin.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Diagnóstico das configurações de e-mail, no perfil de quem administra.
    // Só abre conexão com o servidor SMTP: nenhuma mensagem é enviada.
    Route::post('/profile/email-test', [EmailController::class, 'testConfiguration'])
        ->middleware(['permission:manage users', 'throttle:10,1'])
        ->name('profile.email-test');

    Route::group(['prefix' => 'parking', 
                'middleware' => 'permission:search parking',
            ], function () {
        Route::get('/search', [ParkingController::class, 'search'])->name('parking.search');
        Route::post('/find', [ParkingController::class, 'show'])->name('parking.show');
    });

    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/accesses/{time}', [AccessController::class, 'findAccessByTime'])->name('accesses.findAccessByTime');
    Route::get('/accesses', [AccessController::class, 'index'])->name('accesses.index');
    
    Route::get('/information', [InformationController::class, 'index'])
        ->middleware('permission:view information')->name('information.index');
    Route::get('/information/create', [InformationController::class, 'create'])
        ->middleware('permission:create information')->name('information.create');
    Route::post('/information', [InformationController::class, 'store'])
        ->middleware('permission:create information|edit information')->name('information.store');
    Route::get('/information/{information}', [InformationController::class, 'show'])
        ->middleware('permission:view information')->name('information.show');
    Route::get('/information/{information}/edit', [InformationController::class, 'edit'])
        ->middleware('permission:edit information')->name('information.edit');
    Route::put('/information/{information}', [InformationController::class, 'update'])
        ->middleware('permission:edit information')->name('information.update');
    Route::delete('/information/{information}', [InformationController::class, 'destroy'])
        ->middleware('permission:delete information')->name('information.destroy');

    Route::get('/information/{id}/history', [InformationController::class, 'history'])
        ->middleware('permission:view information')->name('information.history');
    Route::get('/members/{title}', [MemberController::class, 'findMemberByCode'])->name('information.findMemberByCode');

    Route::group(['prefix' => 'company'], function () {
        Route::get('/', [CompanyController::class, 'index'])->name('company.index');
        Route::get('/create', [CompanyController::class, 'create'])->name('company.create');
        Route::post('/', [CompanyController::class, 'store'])->name('company.store');
        Route::get('/access-monitor', [CompanyRulesController::class, 'monitor'])->name('company.access.monitor');
        Route::get('/access-logs', [CompanyRulesController::class, 'accessLogs'])->name('company.access.logs');
        Route::get('/uber-requests', [CompanyRulesController::class, 'uberRequests'])->name('company.uber.requests');
        Route::get('/uber-accesses', [CompanyRulesController::class, 'uberAccesses'])->name('company.uber.accesses');
        Route::get('/workers/search', [WorkerController::class, 'search'])->name('company.worker.search');
        Route::get('/workers/quick-create', [WorkerController::class, 'quickCreate'])->name('company.worker.quick.create');
        Route::post('/workers/quick-create', [WorkerController::class, 'store'])->name('company.worker.quick.store');
        Route::get('/{company}', [CompanyController::class, 'show'])->name('company.show');
        Route::get('/{company}/access-details', [CompanyController::class, 'accessDetails'])->name('company.access-details');
        Route::get('/{company}/edit', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('/{company}', [CompanyController::class, 'update'])->name('company.update');
        Route::delete('/{company}', [CompanyController::class, 'destroy'])->name('company.destroy');

        Route::group(['prefix' => '{company}/worker'], function () {
            Route::get('/create', [WorkerController::class, 'create'])->name('company.worker.create');
            Route::post('/', [WorkerController::class, 'store'])->name('company.worker.store');
            Route::get('/{worker}', [WorkerController::class, 'show'])->name('company.worker.show');
            Route::get('/{worker}/edit', [WorkerController::class, 'edit'])->name('company.worker.edit');
            Route::put('/{worker}', [WorkerController::class, 'update'])->name('company.worker.update');
            Route::delete('/{worker}', [WorkerController::class, 'destroy'])->name('company.worker.destroy');
        });

        Route::group(['prefix' => '{company}/rules'], function () {
            Route::get('/create', [CompanyRulesController::class, 'create'])->name('company.rules.create');
            Route::post('/', [CompanyRulesController::class, 'store'])->name('company.rules.store');
            Route::delete('/', [CompanyRulesController::class, 'bulkDestroy'])->name('company.rules.bulk-destroy');
            Route::get('/{rule}/edit', [CompanyRulesController::class, 'edit'])->name('company.rules.edit');
            Route::put('/{rule}', [CompanyRulesController::class, 'update'])->name('company.rules.update');
            Route::delete('/{rule}', [CompanyRulesController::class, 'destroy'])->name('company.rules.destroy');
        });

        Route::group(['prefix' => '{company}/worker/{worker}/rules'], function () {
            Route::get('/create', [CompanyRulesController::class, 'createForWorker'])->name('company.worker.rules.create');
            Route::post('/', [CompanyRulesController::class, 'store'])->name('company.worker.rules.store');
        });
    });
    
    
    Route::get('/videowall', [VideoWallController::class, 'index'])->name('videowall.index');

    Route::group(['prefix' => 'home-assistant'], function () {
        Route::get('/', [HomeAssistantController::class, 'index'])->name('home-assistant.index');

        // Contactors
        Route::post('/', [HomeAssistantController::class, 'store'])->name('home-assistant.store');
        Route::put('/{contactor}', [HomeAssistantController::class, 'update'])->name('home-assistant.update');
        Route::delete('/{contactor}', [HomeAssistantController::class, 'destroy'])->name('home-assistant.destroy');

        // Ações rápidas por contator
        Route::post('/{contactor}/quick', [HomeAssistantController::class, 'quickAction'])->name('home-assistant.quick');
        Route::post('/{contactor}/quick/clear', [HomeAssistantController::class, 'clearQuick'])->name('home-assistant.quick.clear');

        // Agendamentos (overrides ricos)
        Route::post('/overrides', [HomeAssistantController::class, 'storeOverride'])->name('home-assistant.overrides.store');
        Route::put('/overrides/{override}', [HomeAssistantController::class, 'updateOverride'])->name('home-assistant.overrides.update');
        Route::post('/overrides/{override}/toggle', [HomeAssistantController::class, 'toggleOverride'])->name('home-assistant.overrides.toggle');
        Route::delete('/overrides/{override}', [HomeAssistantController::class, 'destroyOverride'])->name('home-assistant.overrides.destroy');
    });


    Route::resource('place-group', PlaceGroupController::class);

    // Route::resource('place', PlaceController::class);
    Route::group(['prefix' => 'schedule'], function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('/filter', [ScheduleController::class, 'indexFilter'])->name('schedule.index.filter');
        Route::get('/create', [ScheduleController::class, 'create'])->name('schedule.create');
        Route::get('/list', [ScheduleController::class, 'list'])
            ->middleware('permission:view reservations')->name('schedule.list');
        Route::get('/group/{category}/', [PlaceGroupController::class, 'indexByCategory'])->name('api.placegroup.indexByCategory');
        Route::get('/getDates/{place_id?}', [ScheduleRulesController::class, 'getScheduledDates'])->name('schedule.getScheduledDates');
        Route::get('/{id}', [ScheduleController::class, 'show'])->name('schedule.show');
        Route::put('/update', [ScheduleController::class, 'update'])->name('schedule.update');
        Route::post('/store/web', [ScheduleController::class, 'store'])->name('schedule.store.web');
    });

    // Gestão de pagamentos (visualização + estorno via Rede)
    Route::group(['prefix' => 'payments'], function () {
        Route::get('/', [SchedulePaymentController::class, 'index'])
            ->middleware('permission:view payments')->name('payment.index');
        Route::get('/{schedulePayment}', [SchedulePaymentController::class, 'show'])
            ->middleware('permission:view payments')->name('payment.show');
        Route::post('/{schedulePayment}/refund', [SchedulePaymentController::class, 'refund'])
            ->middleware('permission:manage payments')->name('payment.refund');
    });
    
    Route::group(['prefix' => 'place-group'], function () {

        Route::get('/{id}/schedule/rule/create', [PlaceGroupController::class, 'createScheduleRule'])->name('place-group.createScheduleRule');
        Route::post('/schedule/rule', [ScheduleRulesController::class, 'store'])->name('schedule-rules.store');
        Route::get('/schedule/rule/{id}/edit', [PlaceGroupController::class, 'editScheduleRule'])->name('place-group.editScheduleRule');
        Route::put('/schedule/rule/{id}', [PlaceGroupController::class, 'updateScheduleRule'])->name('place-group.updateScheduleRule');
        Route::delete('/schedule/rule/{id}', [PlaceGroupController::class, 'destroyScheduleRule'])->name('place-group.destroyScheduleRule');

        Route::get('/{id}/place/create', [PlaceGroupController::class, 'createPlace'])->name('place-group.createPlace');
        Route::post('/place', [PlaceGroupController::class, 'storePlace'])->name('place-group.storePlace');
        Route::get('/place/{place_id}/edit', [PlaceGroupController::class, 'editPlace'])->name('place-group.editPlace');
        Route::put('/place/{place_id}', [PlaceGroupController::class, 'updatePlace'])->name('place-group.updatePlace');
        Route::delete('/place/{place_id}', [PlaceGroupController::class, 'destroyPlace'])->name('place-group.destroyPlace');
    });

    
    Route::group(['middleware' => 'permission:manage users',], function () {
        Route::group(['prefix' => 'users'], function () {
            Route::get('/', [UserController::class, 'index'])->name('users.index');
            Route::get('/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/', [UserController::class, 'store'])->name('users.store');
            Route::get('/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/{id}', [UserController::class, 'update'])->name('users.update');
            Route::delete('/{id}', [UserController::class, 'destroy'])->name('users.destroy');
        });

        Route::group(['prefix' => 'roles-permission'], function () {
            Route::get('/', [PermissionController::class, 'index'])->name('roles-permission.index');
            Route::get('/create', [PermissionController::class, 'create'])->name('roles-permission.create');
            Route::post('/', [PermissionController::class, 'store'])->name('roles-permission.store');
            Route::get('/{id}', [PermissionController::class, 'show'])->name('roles-permission.show');
            Route::put('/{id}', [PermissionController::class, 'update'])->name('roles-permission.update');
            Route::delete('/{id}', [PermissionController::class, 'destroy'])->name('roles-permission.destroy');
        });

        Route::group(['prefix' => 'sectors'], function () {
            Route::get('/', [SectorController::class, 'index'])->name('sectors.index');
            Route::get('/create', [SectorController::class, 'create'])->name('sectors.create');
            Route::post('/', [SectorController::class, 'store'])->name('sectors.store');
            Route::get('/{id}', [SectorController::class, 'show'])->name('sectors.show');
            Route::put('/{id}', [SectorController::class, 'update'])->name('sectors.update');
            Route::delete('/{id}', [SectorController::class, 'destroy'])->name('sectors.destroy');
            Route::post('/{id}/users', [SectorController::class, 'addUser'])->name('sectors.users.add');
            Route::delete('/{id}/users/{userId}', [SectorController::class, 'removeUser'])->name('sectors.users.remove');
        });
    });

    Route::group(['prefix' => 'comp-time'], function () {
        Route::get('/upload', [CompTimeController::class, 'index'])->name('comp-time.index');
        Route::post('/upload', [CompTimeController::class, 'store'])->name('comp-time.store');
        Route::post('/filter', [CompTimeController::class, 'indexFilter'])->name('comp-time.index.filter');
        Route::post('/details', [CompTimeController::class, 'showDetails'])->name('comp-time.show.details');
        Route::post('/details/day', [CompTimeController::class, 'showDayDetails'])->name('comp-time.show.day.details');
        Route::post('/recalculate', [CompTimeController::class, 'recalculateBalances'])->name('comp-time.recalculate');
        Route::post('/write-off', [CompTimeController::class, 'writeOff'])->name('comp-time.write-off');
        Route::post('/undo-write-off', [CompTimeController::class, 'undoWriteOff'])->name('comp-time.undo-write-off');
        Route::get('/import-status/{uuid}', [CompTimeController::class, 'importStatus'])->name('comp-time.import-status');
        Route::get('/import-status/{uuid}/api', [CompTimeController::class, 'importStatusApi'])->name('comp-time.import-status.api');
        Route::get('/import-status/{uuid}/complete', [CompTimeController::class, 'importComplete'])->name('comp-time.import-complete');
        Route::get('/import-preview/{uuid}', [CompTimeController::class, 'showImportPreview'])->name('comp-time.import-preview');
        Route::post('/confirm-import/{uuid}', [CompTimeController::class, 'confirmImport'])->name('comp-time.confirm-import');
    });


    Route::resource('parking-authorizations', ParkingAuthorizationController::class);

    Route::resource('tournaments', TournamentController::class);

    Route::prefix('categories')->name('categories.')->controller(TournamentController::class)->group(function () {
        Route::get('/', 'indexCategories')->name('index');
        Route::get('/create', 'createCategory')->name('create');
        Route::post('/', 'storeCategory')->name('store');
        Route::get('/{id}/edit', 'editCategory')->name('edit');
        Route::put('/{id}', 'updateCategory')->name('update');
        Route::delete('/{id}', 'destroyCategory')->name('destroy');
    });

    // Leitor de documentação (.md) dentro do painel
    Route::get('/docs', [DocumentationController::class, 'index'])->name('docs.index');
    Route::get('/docs/{slug}', [DocumentationController::class, 'show'])
        ->where('slug', '.*')->name('docs.show');

    // Avisos e Lembretes
    Route::resource('avisos', AvisoController::class);

    // Financeiro dos freelancers — regra própria (Gate de setor, não permissão
    // do Spatie: ver AppServiceProvider), e declarado antes do grupo abaixo para
    // /freelancer-services/financeiro não cair na rota /{freelancerService}.
    Route::group(['middleware' => 'can:manage-freelancer-payments'], function () {
        Route::prefix('freelancer-services')->group(function () {
            // O lote é a unidade de trabalho do financeiro: a lista abre por
            // lote e o pagamento acontece dentro de um deles. As telas
            // `avulsos` e `todos` existem para que nenhum contrato pagável
            // fique invisível — declaradas antes de /lote/{batch} por clareza,
            // já que os prefixos não colidem.
            Route::get('/financeiro', [FreelancerFinanceController::class, 'index'])->name('freelancer-services.finance');
            Route::get('/financeiro/avulsos', [FreelancerFinanceController::class, 'orphans'])->name('freelancer-services.finance.orphans');
            Route::get('/financeiro/todos', [FreelancerFinanceController::class, 'all'])->name('freelancer-services.finance.all');
            Route::get('/financeiro/lote/{batch}', [FreelancerFinanceController::class, 'batch'])->name('freelancer-services.finance.batch');
            Route::get('/financeiro/lote/{batch}/impressao', [FreelancerFinanceController::class, 'print'])->name('freelancer-services.finance.print');
            // Uma rota só: a baixa individual manda `only`, a em massa manda `services[]`.
            Route::post('/pay', [FreelancerFinanceController::class, 'pay'])->name('freelancer-services.pay');
        });
    });

    // Freelancers: cadastro de freelancers, funções e serviços/contratos
    Route::group(['middleware' => 'permission:manage freelancers'], function () {
        Route::prefix('freelancers')->group(function () {
            Route::get('/', [FreelancerWebController::class, 'index'])->name('freelancers.index');
            Route::get('/create', [FreelancerWebController::class, 'create'])->name('freelancers.create');
            Route::post('/', [FreelancerWebController::class, 'store'])->name('freelancers.store');
            // Importação em massa — antes de /{freelancer} para não ser capturada por ela
            Route::get('/import/template', [FreelancerWebController::class, 'importTemplate'])->name('freelancers.import.template');
            Route::post('/import', [FreelancerWebController::class, 'import'])->name('freelancers.import');
            Route::get('/{freelancer}', [FreelancerWebController::class, 'show'])->name('freelancers.show');
            Route::put('/{freelancer}', [FreelancerWebController::class, 'update'])->name('freelancers.update');
            Route::delete('/{freelancer}', [FreelancerWebController::class, 'destroy'])->name('freelancers.destroy');
        });

        Route::prefix('freelancer-functions')->group(function () {
            Route::get('/', [FreelancerFunctionController::class, 'index'])->name('freelancer-functions.index');
            Route::get('/create', [FreelancerFunctionController::class, 'create'])->name('freelancer-functions.create');
            Route::post('/', [FreelancerFunctionController::class, 'store'])->name('freelancer-functions.store');
            Route::get('/{freelancerFunction}', [FreelancerFunctionController::class, 'show'])->name('freelancer-functions.show');
            Route::put('/{freelancerFunction}', [FreelancerFunctionController::class, 'update'])->name('freelancer-functions.update');
            Route::delete('/{freelancerFunction}', [FreelancerFunctionController::class, 'destroy'])->name('freelancer-functions.destroy');
        });

        // Lotes de aprovação — declarados antes de /freelancer-services/{freelancerService}
        // para "lotes" não ser capturado como id de contrato.
        Route::prefix('freelancer-services/lotes')->group(function () {
            // Coordenador: monta o rascunho (um por coordenador) e envia.
            Route::get('/', [FreelancerBatchController::class, 'index'])->name('freelancer-batches.index');
            Route::post('/items', [FreelancerBatchController::class, 'addItems'])->name('freelancer-batches.items.add');
            Route::delete('/items/{freelancerService}', [FreelancerBatchController::class, 'removeItem'])->name('freelancer-batches.items.remove');
            Route::post('/send', [FreelancerBatchController::class, 'send'])->name('freelancer-batches.send');
            Route::delete('/draft', [FreelancerBatchController::class, 'discard'])->name('freelancer-batches.discard');

            // Gerência (coordenador do setor Gerência): fila e análise. Antes
            // de /{batch}, senão "aprovacao" cairia na rota do lote.
            Route::get('/aprovacao', [FreelancerBatchController::class, 'approvalQueue'])->name('freelancer-batches.queue');
            Route::get('/{batch}', [FreelancerBatchController::class, 'show'])->name('freelancer-batches.show');
            Route::post('/{batch}/analise', [FreelancerBatchController::class, 'review'])->name('freelancer-batches.review');

            // Diretoria: e-mail com os dois PINs e registro do PIN ditado.
            // O throttle é a proteção contra tentar adivinhar 6 dígitos.
            Route::post('/{batch}/diretoria/email', [FreelancerBatchController::class, 'notifyDirector'])
                ->middleware('throttle:10,1')->name('freelancer-batches.director.notify');
            Route::post('/{batch}/diretoria', [FreelancerBatchController::class, 'directorDecision'])
                ->middleware('throttle:10,1')->name('freelancer-batches.director.decision');
        });

        Route::prefix('freelancer-services')->group(function () {
            Route::get('/', [FreelancerServiceWebController::class, 'index'])->name('freelancer-services.index');
            Route::get('/create', [FreelancerServiceWebController::class, 'create'])->name('freelancer-services.create');
            // Registro em massa pelo painel (várias linhas, sem planilha) —
            // antes de /{freelancerService} para não cair na rota do serviço.
            Route::get('/em-massa', [FreelancerServiceWebController::class, 'bulkCreate'])->name('freelancer-services.bulk');
            Route::post('/em-massa', [FreelancerServiceWebController::class, 'bulkStore'])->name('freelancer-services.bulk.store');
            Route::post('/', [FreelancerServiceWebController::class, 'store'])->name('freelancer-services.store');
            // Código de liberação do limite semanal por e-mail — declarado antes
            // de /{freelancerService} e com throttle baixo (dispara e-mail).
            Route::post('/weekly-limit-code', [FreelancerServiceWebController::class, 'sendWeeklyLimitCode'])
                ->middleware('throttle:6,1')->name('freelancer-services.weekly-limit-code');
            // Importação em massa — antes de /{freelancerService} para não ser capturada por ela
            Route::get('/import/template', [FreelancerServiceWebController::class, 'importTemplate'])->name('freelancer-services.import.template');
            Route::post('/import', [FreelancerServiceWebController::class, 'import'])->name('freelancer-services.import');
            Route::get('/{freelancerService}', [FreelancerServiceWebController::class, 'show'])->name('freelancer-services.show');
            Route::get('/{freelancerService}/document', [FreelancerServiceWebController::class, 'document'])->name('freelancer-services.document');
            // Imagem da assinatura (freelancer ou coordenador), servida por rota.
            Route::get('/{freelancerService}/signature/{party}', [FreelancerServiceWebController::class, 'signatureImage'])
                ->where('party', 'freelancer|coordinator')->name('freelancer-services.signature');
            Route::put('/{freelancerService}', [FreelancerServiceWebController::class, 'update'])->name('freelancer-services.update');
            Route::delete('/{freelancerService}', [FreelancerServiceWebController::class, 'destroy'])->name('freelancer-services.destroy');
            // A assinatura do coordenador é só pelo kiosk (traço desenhado no
            // tablet) — ver routes de /kiosk. Aqui fica apenas o cancelamento,
            // que exige ser coordenador de setor.
            Route::post('/{freelancerService}/cancel', [FreelancerServiceWebController::class, 'cancel'])->name('freelancer-services.cancel');
        });
    });

    // Carteirinhas (emissão via webcam/impressão em cartão PVC + gestão de modelos)
    Route::group(['middleware' => 'permission:manage id cards'], function () {
        Route::get('/id-cards', [CardIssuerController::class, 'create'])->name('id-cards.issue');

        Route::prefix('card-templates')->group(function () {
            Route::get('/', [CardTemplateController::class, 'index'])->name('card-templates.index');
            Route::get('/create', [CardTemplateController::class, 'create'])->name('card-templates.create');
            Route::post('/', [CardTemplateController::class, 'store'])->name('card-templates.store');
            Route::get('/{cardTemplate}/edit', [CardTemplateController::class, 'edit'])->name('card-templates.edit');
            Route::put('/{cardTemplate}', [CardTemplateController::class, 'update'])->name('card-templates.update');
            Route::delete('/{cardTemplate}', [CardTemplateController::class, 'destroy'])->name('card-templates.destroy');
        });
    });

    // Lara — chat interno com o agente de IA do estatuto.
    // O throttle é baixo de propósito: cada pergunta pode segurar um worker do
    // PHP-FPM por até 30s enquanto o modelo (que roda em CPU) responde.
    Route::prefix('lara')->middleware('permission:use lara chat')->group(function () {
        Route::get('/', [LaraChatController::class, 'index'])->name('lara.index');
        Route::post('/perguntar', [LaraChatController::class, 'ask'])
            ->middleware('throttle:15,1')->name('lara.ask');
        Route::post('/reiniciar', [LaraChatController::class, 'reset'])
            ->middleware('throttle:15,1')->name('lara.reset');
    });

    // Notificações
    Route::get('/notifications/unread-json', [NotificationController::class, 'unreadJson'])->name('notifications.unreadJson');
    Route::get('/notifications/{id}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.markRead');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');

});

require __DIR__.'/auth.php';

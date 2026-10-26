<?php

use App\Authorization\Permissions as P;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SiteAccountController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Company\CompanyWorkerController as WorkerController;
use App\Http\Controllers\Company\CompanyAccessRulesController as CompanyRulesController;
use App\Http\Controllers\Company\OneOffAccessController;
use App\Http\Controllers\Tournament\CategoryTemplateController;
use App\Http\Controllers\Tournament\TournamentBracketController;
use App\Http\Controllers\Tournament\TournamentFinanceController;
use App\Http\Controllers\Tournament\TournamentResultController;
use App\Http\Controllers\Tournament\TournamentCategoryController;
use App\Http\Controllers\Tournament\TournamentController;
use App\Http\Controllers\Tournament\TournamentRegistrationController;
use App\Http\Controllers\Tournament\TournamentTeamController;

use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SchedulePaymentController;
use App\Http\Controllers\ScheduleRulesController;
use App\Http\Controllers\Social\SocialBookingController;
use App\Http\Controllers\Social\SocialBeverageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\MySectorController;
use App\Http\Controllers\CompTimeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ParkingAuthorizationController;
use App\Http\Controllers\Fleet\FleetController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\EmailController;

use App\Http\Controllers\AvisoController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Freelancer\FinanceController as FreelancerFinanceController;
use App\Http\Controllers\Freelancer\FreelancerController as FreelancerWebController;
use App\Http\Controllers\Freelancer\FunctionController as FreelancerFunctionController;
use App\Http\Controllers\Freelancer\BatchController as FreelancerBatchController;
use App\Http\Controllers\Freelancer\TrackingController as FreelancerTrackingController;
use App\Http\Controllers\Freelancer\ServiceController as FreelancerServiceWebController;
use App\Http\Controllers\Freelancer\KioskController;
use App\Http\Controllers\Freelancer\DirectorController as FreelancerDirectorController;
use App\Http\Controllers\Freelancer\ValidationController as FreelancerValidationController;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaraChatController;
use App\Http\Controllers\HomeAssistantController;
use App\Http\Controllers\CardIssuerController;
use App\Http\Controllers\CardTemplateController;
use App\Http\Controllers\Questor\PurchaseOrderController as QuestorPurchaseOrderController;
use App\Http\Controllers\Questor\CostCenterApproverController as QuestorCostCenterController;
use App\Http\Controllers\Cotacao\MapaController as CotacaoMapaController;
use App\Http\Controllers\Cotacao\PrecoController as CotacaoPrecoController;
use App\Http\Controllers\Cotacao\FornecedorController as CotacaoFornecedorController;

use App\Http\Controllers\Placar\Web\EquipeController as PlacarEquipeWebController;
use App\Http\Controllers\Placar\Web\TimeController as PlacarTimeWebController;
use App\Http\Controllers\Placar\Web\ElencoController as PlacarElencoWebController;
use App\Http\Controllers\Placar\Web\JogadorController as PlacarJogadorWebController;
use App\Http\Controllers\Placar\Web\CompeticaoController as PlacarCompeticaoWebController;
use App\Http\Controllers\Placar\Web\JogoController as PlacarJogoWebController;
use App\Http\Controllers\Placar\Web\EscalacaoController as PlacarEscalacaoWebController;
use App\Http\Controllers\Placar\Web\ScoutController as PlacarScoutWebController;
use App\Http\Controllers\Replay\Web\SettingController as ReplaySettingController;
use App\Http\Controllers\Replay\Web\LayoutController as ReplayLayoutController;
use App\Http\Controllers\Replay\Web\CameraController as ReplayCameraController;
use App\Http\Controllers\Replay\Web\VideoController as ReplayVideoWebController;
use App\Http\Controllers\Replay\VideoFileController as ReplayVideoFileController;

use App\Http\Controllers\PoliBot\AttendanceEventController as PoliBotAttendanceEventController;
use App\Http\Controllers\PoliBot\FlowController as PoliBotFlowController;
use App\Http\Controllers\PoliBot\PoliDataController as PoliBotDataController;
use App\Http\Controllers\PoliBot\SimulatorController as PoliBotSimulatorController;

use App\Http\Controllers\Signature\AttachmentController as SignatureAttachmentController;
use App\Http\Controllers\Signature\DirectorAreaController as SignatureDirectorAreaController;
use App\Http\Controllers\Signature\DirectorKioskController as SignatureDirectorKioskController;
use App\Http\Controllers\Signature\DocumentController as SignatureDocumentController;
use App\Http\Controllers\Signature\GovbrController as SignatureGovbrController;
use App\Http\Controllers\Signature\GuideController as SignatureGuideController;
use App\Http\Controllers\Signature\LayoutController as SignatureLayoutController;
use App\Http\Controllers\Signature\MinorTermController;
use App\Http\Controllers\Signature\MinorTermKioskController;
use App\Http\Controllers\Signature\QuiosqueController;
use App\Http\Controllers\Signature\ReleaseController as SignatureReleaseController;
use App\Http\Controllers\Signature\RemoteLinkController as SignatureRemoteLinkController;
use App\Http\Controllers\Signature\ReviewController as SignatureReviewController;
use App\Http\Controllers\Signature\TemplateController as SignatureTemplateController;
use App\Http\Controllers\Signature\ValidationController as SignatureValidationController;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'avisos_obrigatorios'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Quiosque de assinatura (tablet do balcão) — PÚBLICO, sem sessão web
|--------------------------------------------------------------------------
|
| Não confundir com o `/kiosk` logo abaixo, que é outro aparelho e outro
| assunto: lá o OPERADOR entra com matrícula e PIN e atende vários contratos
| de freelancer; aqui o tablet não entra em lugar nenhum — ele lê um QR Code
| que libera UM documento para UMA assinatura, e volta à tela de espera.
|
| A porta é `/assinatura/kiosk/consumir`: recebe o conteúdo do QR, consome o token (uso
| único) e devolve o cookie `lara_sign`. Dali em diante, o middleware
| `signature_kiosk` resolve esse cookie, confere o prazo e amarra a requisição
| ao documento da sessão — qualquer outro id responde 403.
|
| O rate limiting do consumo é o mais apertado do módulo: é a única rota deste
| sistema em que um token pode ser adivinhado.
|
*/
// Endereço antigo da tela do tablet: um aparelho já configurado com ele cai
// no endereço novo em vez de numa página de erro.
Route::redirect('/quiosque', '/assinatura/kiosk');

/*
| O endereço é `/assinatura/kiosk`, junto do resto do módulo. Os NOMES das
| rotas continuam `quiosque.*` — são internos, e é por eles que o código e os
| testes as chamam. `assinatura/modelos` e `assinatura/documentos` ficam no
| grupo `auth`, mais abaixo; este prefixo é público de propósito.
*/
Route::prefix('assinatura/kiosk')->name('quiosque.')->group(function () {
    Route::get('/', [QuiosqueController::class, 'index'])->name('index');

    Route::post('/consumir', [QuiosqueController::class, 'consume'])
        ->middleware('throttle:10,1')->name('consume');

    /*
    | Termo de Menores — autoatendimento do sócio nos eventos. A mesma tela do
    | quiosque, no modo "menores": o próprio tablet gera o documento (título →
    | responsável → CPF → menor) e recebe o cookie `lara_sign`; dali em diante
    | usa as rotas do quiosque abaixo. Só responde a tablet pareado
    | (`signature_minor_device`, pareamento por QR em Assinaturas → Termo de
    | Menores). Ver MinorTermKioskController.
    */
    Route::prefix('/menores')->name('menores.')->group(function () {
        Route::get('/', [MinorTermKioskController::class, 'index'])->name('index');

        // Leitura do QR de pareamento — token adivinhável só por força bruta.
        Route::post('/parear', [MinorTermKioskController::class, 'pair'])
            ->middleware('throttle:10,1')->name('pair');

        Route::middleware('signature_minor_device')->group(function () {
            Route::get('/estado', [MinorTermKioskController::class, 'state'])
                ->middleware('throttle:120,1')->name('state');
            Route::post('/titulo', [MinorTermKioskController::class, 'title'])
                ->middleware('throttle:30,1')->name('title');
            // O serviço conta as tentativas de CPF por título; o throttle é a
            // trava por IP.
            Route::post('/responsavel', [MinorTermKioskController::class, 'responsible'])
                ->middleware('throttle:20,1')->name('responsible');
            Route::get('/lista', [MinorTermKioskController::class, 'minors'])
                ->middleware('throttle:60,1')->name('minors');
            Route::post('/documento', [MinorTermKioskController::class, 'document'])
                ->middleware('throttle:20,1')->name('document');
            Route::post('/encerrar', [MinorTermKioskController::class, 'end'])
                ->middleware('throttle:60,1')->name('end');
        });
    });

    /*
    | Assinatura da Diretoria — o diretor entra no tablet com matrícula e PIN
    | do Lara (cookie `lara_director`), vê os documentos pendentes da
    | assinatura dele e abre um, recebendo o `lara_sign` como na leitura do QR.
    | O limite de tentativas de PIN é por MATRÍCULA, no serviço; o throttle é a
    | trava por IP. Ver DirectorKioskController.
    */
    Route::prefix('/diretoria')->name('diretoria.')->group(function () {
        Route::post('/entrar', [SignatureDirectorKioskController::class, 'login'])
            ->middleware('throttle:20,1')->name('login');
        Route::get('/pendentes', [SignatureDirectorKioskController::class, 'pending'])
            ->middleware('throttle:120,1')->name('pending');
        Route::post('/abrir/{signatureSigner}', [SignatureDirectorKioskController::class, 'open'])
            ->whereNumber('signatureSigner')->middleware('throttle:20,1')->name('open');
        // Assinatura em lote (vários documentos de uma vez).
        Route::post('/lote', [SignatureDirectorKioskController::class, 'openBatch'])
            ->middleware('throttle:20,1')->name('batch.open');
        Route::get('/lote/{signatureSigner}/pdf', [SignatureDirectorKioskController::class, 'batchPdf'])
            ->whereNumber('signatureSigner')->middleware('throttle:120,1')->name('batch.pdf');
        Route::post('/lote/{signatureSigner}/visualizado', [SignatureDirectorKioskController::class, 'batchViewed'])
            ->whereNumber('signatureSigner')->middleware('throttle:120,1')->name('batch.viewed');
        Route::post('/lote/assinar', [SignatureDirectorKioskController::class, 'signBatch'])
            ->middleware('throttle:10,1')->name('batch.sign');
        Route::post('/lote/cancelar', [SignatureDirectorKioskController::class, 'cancelBatch'])
            ->middleware('throttle:30,1')->name('batch.cancel');
        Route::post('/sair', [SignatureDirectorKioskController::class, 'logout'])
            ->middleware('throttle:60,1')->name('logout');
    });

    Route::middleware('signature_kiosk')->group(function () {
        Route::get('/sessao', [QuiosqueController::class, 'session'])
            ->middleware('throttle:120,1')->name('session');

        Route::post('/encerrar', [QuiosqueController::class, 'leave'])->name('leave');

        Route::prefix('/documento/{signatureDocument}')->whereNumber('signatureDocument')->group(function () {
            Route::get('/pdf', [QuiosqueController::class, 'pdf'])->name('pdf');

            // Respostas de quem assina às perguntas do modelo. Refaz o PDF a
            // cada envio, daí o teto.
            Route::post('/respostas', [QuiosqueController::class, 'answers'])
                ->middleware('throttle:20,1')->name('answers');
            Route::post('/visualizado', [QuiosqueController::class, 'viewed'])
                ->middleware('throttle:60,1')->name('viewed');

            // Teto baixo: no modo parcial a conferência é de quatro dígitos, e
            // o contador por solicitação (identity_attempts) fecha a sessão
            // antes disso. As duas travas existem porque o throttle é por IP —
            // e o tablet inteiro divide um IP só.
            Route::post('/identidade', [QuiosqueController::class, 'identity'])
                ->middleware('throttle:10,1')->name('identity');

            // Código da conferência por e-mail: o serviço limita a 3 envios
            // por liberação, um por minuto; o throttle é a trava por IP.
            Route::post('/identidade/codigo', [QuiosqueController::class, 'identityCode'])
                ->middleware('throttle:6,1')->name('identity-code');

            Route::post('/assinar', [QuiosqueController::class, 'sign'])
                ->middleware('throttle:10,1')->name('sign');

            Route::post('/recusar', [QuiosqueController::class, 'refuse'])
                ->middleware('throttle:20,1')->name('refuse');
        });
    });
});

/*
| Validação pública de documento assinado. Rota curta e sem autenticação de
| propósito: quem chega aqui veio do QR impresso no manifesto, com o papel na
| mão. Mostra pouco (ver ValidationController) e o arquivo enviado para
| conferência nunca é gravado.
*/
Route::get('/validar/{codigo}', [SignatureValidationController::class, 'show'])
    ->where('codigo', '[A-Za-z0-9]{6,24}')
    ->middleware('throttle:60,1')
    ->name('signature.validate');

Route::post('/validar/{codigo}/conferir', [SignatureValidationController::class, 'verify'])
    ->where('codigo', '[A-Za-z0-9]{6,24}')
    ->middleware('throttle:30,1')
    ->name('signature.validate.verify');

/*
| Replay — clipe cuja cópia local sumiu. Pública como a galeria da quadra no
| site de locação, que aponta para cá: refaz a cópia a partir do FTP e
| redireciona para o arquivo estático. Endereçado pelo uuid, nunca pelo id.
*/
Route::get('/replay/arquivo/{uuid}', [ReplayVideoFileController::class, 'show'])
    ->whereUuid('uuid')
    ->middleware('throttle:120,1')
    ->name('replay.videos.file');

// Kiosk de assinatura (tablet) — AUTENTICAÇÃO PRÓPRIA, fora da sessão web.
// Entra-se com matrícula + PIN; a própria sessão de kiosk (operator_id + mode)
// protege os endpoints. Atendem freelancers os usuários com a permissão
// `freelancers.assinatura`; assinam como contraparte os coordenadores do setor
// Comercial.
Route::prefix('kiosk')->group(function () {
    Route::get('/', [KioskController::class, 'index'])->name('kiosk.index');
    Route::get('/session', [KioskController::class, 'session'])->name('kiosk.session');
    Route::post('/login', [KioskController::class, 'login'])->middleware('throttle:10,1')->name('kiosk.login');
    Route::post('/mode', [KioskController::class, 'mode'])->name('kiosk.mode');
    Route::post('/logout', [KioskController::class, 'logout'])->name('kiosk.logout');
    Route::get('/functions', [KioskController::class, 'functions'])->name('kiosk.functions');
    // Busca por função: quem já atuou nela, bloqueando quem está no limite semanal.
    Route::get('/functions/{functionFreelancer}/freelancers', [KioskController::class, 'searchByFunction'])
        ->name('kiosk.functions.freelancers');
    Route::get('/freelancer/{cpf}', [KioskController::class, 'findFreelancer'])
        ->where('cpf', '[0-9]{11}')->name('kiosk.freelancer.find');
    Route::post('/freelancer', [KioskController::class, 'storeFreelancer'])->name('kiosk.freelancer.store');
    // Completar o cadastro no tablet destrava a geração do contrato.
    Route::put('/freelancer/{freelancer}', [KioskController::class, 'updateFreelancer'])->name('kiosk.freelancer.update');
    // Correção da chave PIX na conferência que antecede a assinatura. Rota
    // própria, e não parte do cadastro: mudar para onde o dinheiro vai é o ato
    // que precisa ficar registrado sozinho.
    Route::put('/freelancer/{freelancer}/pix-key', [KioskController::class, 'updatePixKey'])
        ->middleware('throttle:20,1')->name('kiosk.freelancer.pix-key');
    Route::get('/freelancer/{freelancer}/services', [KioskController::class, 'services'])->name('kiosk.freelancer.services');
    // Contratos de um dia, de todos os freelancers — a conferência de quem
    // estava escalado e quem não apareceu. Só de hoje para trás.
    Route::get('/services/day', [KioskController::class, 'dayServices'])->name('kiosk.services.day');
    Route::post('/service', [KioskController::class, 'storeService'])
        ->middleware('throttle:20,1')->name('kiosk.service.store');
    // Código de liberação do limite semanal por e-mail. Throttle baixo: é um
    // e-mail para uma caixa de terceiro, não um endpoint de consulta.
    Route::post('/service/weekly-limit-code', [KioskController::class, 'weeklyLimitCode'])
        ->middleware('throttle:6,1')->name('kiosk.service.weekly-limit-code');
    // Falta do freelancer: o turno não foi cumprido. Baixa o contrato e devolve
    // a vaga da semana — é a saída para quem faltou num dia e veio em outro,
    // sem gastar a liberação do coordenador com um problema de cadastro.
    Route::post('/service/{freelancerService}/no-show', [KioskController::class, 'markNoShow'])
        ->middleware('throttle:20,1')->name('kiosk.service.no-show');
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
    // Jantar do turno noturno: a pergunta que o tablet faz logo depois da
    // assinatura, para a cozinha saber quantos pratos preparar.
    Route::post('/service/{freelancerService}/dinner', [KioskController::class, 'dinnerAnswer'])
        ->middleware('throttle:20,1')->name('kiosk.service.dinner');

    // O documento é montado pelo SERVIDOR, pelo mesmo Blade que o painel
    // imprime — o tablet apenas o exibe. Com o texto do contrato em dois
    // lugares, cada revisão do jurídico teria de ser escrita duas vezes.
    Route::get('/service/{freelancerService}/document', [KioskController::class, 'document'])
        ->name('kiosk.service.document');

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

/*
|--------------------------------------------------------------------------
| Painel (sessão web)
|--------------------------------------------------------------------------
|
| O acesso é por permissão do catálogo (App\Authorization\Permissions),
| sempre na ROTA — esconder o item do menu não impede um POST direto. O
| middleware é `can:<permissão>`: passa pelo Gate::before do
| AppServiceProvider, que decide pelo setor, pela permissão individual ou
| pelo acesso total. Rota sem `can:` é aberta a qualquer usuário logado —
| hoje: dashboard, perfil, documentação, InfoClube (leitura), Avisos,
| Empresas, Monitor de Acesso, notificações e "Meu setor".
*/
Route::middleware(['auth', 'avisos_obrigatorios'])->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/pin', [ProfileController::class, 'updatePin'])->name('profile.pin.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Favoritos e ordem do menu, gravados na conta (o layout chama por URL
    // fixa, e não por route(), para um cache de rotas velho não derrubar a tela).
    Route::put('/nav-preferences', [\App\Http\Controllers\NavPreferencesController::class, 'update'])->name('nav-preferences.update');

    // Diagnóstico das configurações de e-mail, no perfil de quem administra.
    // Só abre conexão com o servidor SMTP: nenhuma mensagem é enviada.
    Route::post('/profile/email-test', [EmailController::class, 'testConfiguration'])
        ->middleware(['can:' . P::USUARIOS_GERENCIAR, 'throttle:10,1'])
        ->name('profile.email-test');

    Route::group(['prefix' => 'parking', 'middleware' => 'can:' . P::SIV_BUSCA], function () {
        Route::get('/search', [ParkingController::class, 'search'])->name('parking.search');
        Route::post('/find', [ParkingController::class, 'show'])->name('parking.show');
    });

    // Consulta de sócios e de acessos: fora do menu e sem tela que as chame.
    // Fechadas para quem tem acesso total até alguém precisar — aí a
    // permissão é dada ao setor na tela de Setores.
    Route::middleware('can:' . P::SOCIOS_CONSULTA)->group(function () {
        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::get('/accesses/{time}', [AccessController::class, 'findAccessByTime'])->name('accesses.findAccessByTime');
        Route::get('/accesses', [AccessController::class, 'index'])->name('accesses.index');
        Route::get('/members/{title}', [MemberController::class, 'findMemberByCode'])->name('information.findMemberByCode');
    });

    // InfoClube: ler é de todo mundo logado; escrever é da permissão.
    Route::get('/information', [InformationController::class, 'index'])->name('information.index');
    Route::middleware('can:' . P::INFOCLUBE_EDITAR)->group(function () {
        Route::get('/information/create', [InformationController::class, 'create'])->name('information.create');
        Route::post('/information', [InformationController::class, 'store'])->name('information.store');
        Route::get('/information/{information}/edit', [InformationController::class, 'edit'])->name('information.edit');
        Route::put('/information/{information}', [InformationController::class, 'update'])->name('information.update');
        Route::delete('/information/{information}', [InformationController::class, 'destroy'])->name('information.destroy');
    });
    Route::get('/information/{information}', [InformationController::class, 'show'])->name('information.show');
    Route::get('/information/{id}/history', [InformationController::class, 'history'])->name('information.history');

    // Externos. Empresas (com prestadores e regras) e o Monitor de Acesso são
    // de todo mundo logado; Liberação Pontual, Histórico e Carros de
    // Aplicativo têm permissão própria.
    Route::group(['prefix' => 'company'], function () {
        Route::get('/', [CompanyController::class, 'index'])->name('company.index');
        Route::get('/create', [CompanyController::class, 'create'])->name('company.create');
        Route::post('/', [CompanyController::class, 'store'])->name('company.store');
        Route::get('/access-monitor', [CompanyRulesController::class, 'monitor'])->name('company.access.monitor');
        Route::get('/access-logs', [CompanyRulesController::class, 'accessLogs'])
            ->middleware('can:' . P::EXTERNOS_HISTORICO)->name('company.access.logs');

        Route::middleware('can:' . P::EXTERNOS_CARROS_APLICATIVO)->group(function () {
            Route::get('/uber-requests', [CompanyRulesController::class, 'uberRequests'])->name('company.uber.requests');
            Route::get('/uber-accesses', [CompanyRulesController::class, 'uberAccesses'])->name('company.uber.accesses');
            // Painel da portaria: a fila de pedidos esperando o motorista
            // chegar. Fora do menu — abre pela tela de Carros de Aplicativo.
            Route::get('/uber-waiting', [CompanyRulesController::class, 'uberWaiting'])->name('company.uber.waiting');
        });

        // Liberação pontual — acima do `/{company}`, que engoliria o segmento.
        Route::middleware('can:' . P::EXTERNOS_LIBERACAO_PONTUAL)->group(function () {
            Route::get('/one-off-accesses', [OneOffAccessController::class, 'index'])->name('company.one-off.index');
            Route::get('/one-off-accesses/create', [OneOffAccessController::class, 'create'])->name('company.one-off.create');
            Route::post('/one-off-accesses', [OneOffAccessController::class, 'store'])->name('company.one-off.store');
            Route::patch('/one-off-accesses/{oneOffAccess}/cancel', [OneOffAccessController::class, 'cancel'])->name('company.one-off.cancel');
        });

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

    Route::group(['prefix' => 'home-assistant', 'middleware' => 'can:' . P::HOME_ASSISTANT], function () {
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

        // Autoatendimento do sócio: horários, exceções por quadra e feriados.
        Route::post('/self-service/windows', [HomeAssistantController::class, 'saveSelfServiceWindows'])->name('home-assistant.self-service.windows.save');
        Route::post('/self-service/windows/{place}', [HomeAssistantController::class, 'saveSelfServicePlaceWindows'])->name('home-assistant.self-service.windows.place');
        Route::delete('/self-service/windows/{place}', [HomeAssistantController::class, 'resetSelfServicePlaceWindows'])->name('home-assistant.self-service.windows.reset');
        Route::post('/self-service/dates', [HomeAssistantController::class, 'storeSelfServiceDate'])->name('home-assistant.self-service.dates.store');
        Route::delete('/self-service/dates/{date}', [HomeAssistantController::class, 'destroySelfServiceDate'])->name('home-assistant.self-service.dates.destroy');
    });

    // Configuração das reservas: locais, grupos de local e regras. Fora do
    // menu; o que a tela de agendamento consulta (datas, grupos por
    // categoria) fica no grupo `schedule` abaixo, com a permissão dela.
    Route::middleware('can:' . P::RESERVAS_CONFIGURAR)->group(function () {
        Route::resource('place-group', PlaceGroupController::class);

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
    });

    Route::group(['prefix' => 'schedule', 'middleware' => 'can:' . P::RESERVAS_AGENDAMENTOS], function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('schedule.index');
        Route::post('/filter', [ScheduleController::class, 'indexFilter'])->name('schedule.index.filter');
        Route::get('/create', [ScheduleController::class, 'create'])->name('schedule.create');
        Route::get('/list', [ScheduleController::class, 'list'])->name('schedule.list');
        Route::get('/group/{category}/', [PlaceGroupController::class, 'indexByCategory'])->name('api.placegroup.indexByCategory');
        Route::get('/getDates/{place_id?}', [ScheduleRulesController::class, 'getScheduledDates'])->name('schedule.getScheduledDates');
        Route::get('/{id}', [ScheduleController::class, 'show'])->name('schedule.show');
        Route::put('/update', [ScheduleController::class, 'update'])->name('schedule.update');
        Route::post('/store/web', [ScheduleController::class, 'store'])->name('schedule.store.web');
    });

    // Reservas de espaços sociais (churrasqueiras, salões). Pré-reserva →
    // contrato → aprovação → pagamento no app. Rotas fixas antes de
    // /{booking}, senão o segmento é lido como reserva. O catálogo de bebidas
    // é configuração (`reservas.configurar`), mas mora sob o mesmo prefixo
    // para a tela abrir com as abas de Reservas.
    Route::group(['prefix' => 'social-bookings', 'as' => 'social-booking.'], function () {
        Route::middleware('can:' . P::RESERVAS_CONFIGURAR)->prefix('bebidas')->as('beverages.')->group(function () {
            Route::get('/', [SocialBeverageController::class, 'index'])->name('index');
            Route::post('/', [SocialBeverageController::class, 'store'])->name('store');
            Route::put('/{beverage}', [SocialBeverageController::class, 'update'])->name('update');
            Route::delete('/{beverage}', [SocialBeverageController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('can:' . P::RESERVAS_SOCIAIS)->group(function () {
            Route::get('/', [SocialBookingController::class, 'index'])->name('index');
            Route::get('/create', [SocialBookingController::class, 'create'])->name('create');
            Route::post('/', [SocialBookingController::class, 'store'])->name('store');
            Route::get('/availability', [SocialBookingController::class, 'availability'])->name('availability');
            Route::get('/{booking}', [SocialBookingController::class, 'show'])->name('show');
            Route::put('/{booking}/details', [SocialBookingController::class, 'updateDetails'])->name('details');
            Route::put('/{booking}/guests', [SocialBookingController::class, 'updateGuests'])->name('guests');
            Route::get('/{booking}/guests.pdf', [SocialBookingController::class, 'guestsPdf'])->name('guests.pdf');
            Route::get('/{booking}/contract', [SocialBookingController::class, 'downloadContract'])->name('contract.download');
            Route::post('/{booking}/contact', [SocialBookingController::class, 'contact'])->name('contact');
        });

        Route::middleware('can:' . P::RESERVAS_SOCIAIS_APROVAR)->group(function () {
            Route::post('/{booking}/contract', [SocialBookingController::class, 'contract'])->name('contract');
            Route::post('/{booking}/approve', [SocialBookingController::class, 'approve'])->name('approve');
            Route::post('/{booking}/refuse', [SocialBookingController::class, 'refuse'])->name('refuse');
            Route::post('/{booking}/cancel', [SocialBookingController::class, 'cancel'])->name('cancel');
        });
    });

    // Pagamentos das reservas (visualização + estorno via Rede). No menu é
    // uma sub-aba de Reservas; a URL continua a mesma.
    Route::group(['prefix' => 'payments'], function () {
        Route::middleware('can:' . P::RESERVAS_PAGAMENTOS)->group(function () {
            Route::get('/', [SchedulePaymentController::class, 'index'])->name('payment.index');
            Route::get('/{schedulePayment}', [SchedulePaymentController::class, 'show'])->name('payment.show');
        });
        Route::post('/{schedulePayment}/refund', [SchedulePaymentController::class, 'refund'])
            ->middleware('can:' . P::RESERVAS_PAGAMENTOS_ESTORNAR)->name('payment.refund');
        // Baixa manual (pago fora da plataforma). Os botões ficam na tela do
        // agendamento e na da reserva social; não pede ver Pagamentos.
        Route::post('/manual', [SchedulePaymentController::class, 'storeManual'])
            ->middleware('can:' . P::RESERVAS_PAGAMENTOS_BAIXA_MANUAL)->name('payment.manual');
    });

    // Gestão de acesso. Usuários e Setores são de quem tem a permissão (na
    // prática, os setores de acesso total). "Meu setor" é de qualquer
    // coordenador, e o controller restringe aos setores que ele coordena.
    Route::group(['prefix' => 'users', 'middleware' => 'can:' . P::USUARIOS_GERENCIAR], function () {
        Route::get('/', [UserController::class, 'index'])->name('users.index');
        Route::get('/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/', [UserController::class, 'store'])->name('users.store');
        Route::get('/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/{id}', [UserController::class, 'update'])->name('users.update');
        Route::put('/{id}/permissions', [UserController::class, 'updatePermissions'])->name('users.permissions.update');
        Route::put('/{id}/sectors', [UserController::class, 'updateSectors'])->name('users.sectors.update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    Route::group(['prefix' => 'sectors', 'middleware' => 'can:' . P::SETORES_GERENCIAR], function () {
        Route::get('/', [SectorController::class, 'index'])->name('sectors.index');
        Route::get('/create', [SectorController::class, 'create'])->name('sectors.create');
        Route::get('/audit', [SectorController::class, 'audit'])->name('sectors.audit');
        Route::post('/', [SectorController::class, 'store'])->name('sectors.store');
        Route::get('/{id}', [SectorController::class, 'show'])->name('sectors.show');
        Route::put('/{id}', [SectorController::class, 'update'])->name('sectors.update');
        Route::put('/{id}/permissions', [SectorController::class, 'updatePermissions'])->name('sectors.permissions.update');
        Route::delete('/{id}', [SectorController::class, 'destroy'])->name('sectors.destroy');
        Route::post('/{id}/users', [SectorController::class, 'addUser'])->name('sectors.users.add');
        Route::delete('/{id}/users/{userId}', [SectorController::class, 'removeUser'])->name('sectors.users.remove');
    });

    Route::prefix('meu-setor')->name('my-sector.')->group(function () {
        Route::get('/', [MySectorController::class, 'index'])->name('index');
        Route::get('/{sector}', [MySectorController::class, 'show'])->name('show');
        Route::post('/{sector}/membros', [MySectorController::class, 'addMember'])->middleware('throttle:30,1')->name('members.add');
        Route::post('/{sector}/usuarios', [MySectorController::class, 'createUser'])->middleware('throttle:10,1')->name('users.store');
        Route::delete('/{sector}/membros/{user}', [MySectorController::class, 'removeMember'])->name('members.remove');
    });

    // Banco de Horas. A leitura é aberta a qualquer autenticado e o recorte é
    // feito dentro do controller (CompTimeService::accessFor): RH vê todos,
    // coordenador vê o próprio setor, colaborador vê a própria ficha, quem não
    // tem matrícula não vê nada.
    Route::group(['prefix' => 'comp-time'], function () {
        Route::get('/upload', [CompTimeController::class, 'index'])->name('comp-time.index');
        Route::post('/filter', [CompTimeController::class, 'indexFilter'])->name('comp-time.index.filter');
        Route::post('/details', [CompTimeController::class, 'showDetails'])->name('comp-time.show.details');
        Route::post('/details/day', [CompTimeController::class, 'showDayDetails'])->name('comp-time.show.day.details');
        Route::post('/write-off', [CompTimeController::class, 'writeOff'])->name('comp-time.write-off');
        Route::post('/undo-write-off', [CompTimeController::class, 'undoWriteOff'])->name('comp-time.undo-write-off');

        // Importação e cadastro: permissão `banco-horas.admin` (setor RH na
        // matriz inicial, ou individual).
        Route::group(['middleware' => 'can:' . P::BANCO_HORAS_ADMIN], function () {
            Route::post('/upload', [CompTimeController::class, 'store'])->name('comp-time.store');
            Route::post('/recalculate', [CompTimeController::class, 'recalculateBalances'])->name('comp-time.recalculate');
            Route::get('/import-status/{uuid}', [CompTimeController::class, 'importStatus'])->name('comp-time.import-status');
            Route::get('/import-status/{uuid}/api', [CompTimeController::class, 'importStatusApi'])->name('comp-time.import-status.api');
            Route::get('/import-status/{uuid}/complete', [CompTimeController::class, 'importComplete'])->name('comp-time.import-complete');
            Route::get('/import-preview/{uuid}', [CompTimeController::class, 'showImportPreview'])->name('comp-time.import-preview');
            Route::post('/confirm-import/{uuid}', [CompTimeController::class, 'confirmImport'])->name('comp-time.confirm-import');
        });

        // O cadastro de funcionários saiu daqui para o módulo Funcionários;
        // os endereços antigos só redirecionam.
        Route::redirect('/employees', '/funcionarios')->name('comp-time.employees.index');
        Route::redirect('/employees/{employee}', '/funcionarios/{employee}')->name('comp-time.employees.show');
    });

    // Funcionários: o cadastro do RH. É ele que autentica quem pede carro de
    // aplicativo (Bot WhatsApp) e quem dirige a frota (SIV) — ver
    // docs/funcionalidades/funcionarios.md. `import` antes de `{employee}`
    // para não casar como id.
    Route::prefix('funcionarios')->name('employees.')->middleware('can:' . P::FUNCIONARIOS_GERENCIAR)
        ->controller(EmployeeController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/novo', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/import/template', 'importTemplate')->name('import.template');
            Route::post('/import', 'import')->name('import');
            Route::get('/{employee}', 'show')->name('show')->withTrashed();
            Route::put('/{employee}', 'update')->name('update');
            Route::delete('/{employee}', 'destroy')->name('destroy');
            Route::post('/{employee}/restaurar', 'restore')->name('restore')->withTrashed();
            Route::post('/{employee}/ausencias', 'storeAbsence')->name('absences.store');
            Route::delete('/{employee}/ausencias/{absence}', 'destroyAbsence')->name('absences.destroy');
        });


    // Frota: painel de saída/retorno, histórico de quilometragem e cadastro
    // dos veículos. O registro daqui usa o mesmo serviço da API da portaria.
    // Uma permissão por tela: hoje os três vão juntos para os mesmos setores,
    // mas podem se separar na tela de Setores sem mexer em rota.
    Route::group(['prefix' => 'fleet'], function () {
        Route::middleware('can:' . P::SIV_FROTA)->group(function () {
            Route::get('/', [FleetController::class, 'index'])->name('fleet.index');
            Route::post('/departure', [FleetController::class, 'storeDeparture'])->name('fleet.departure');
            Route::post('/return', [FleetController::class, 'storeReturn'])->name('fleet.return');
        });

        Route::middleware('can:' . P::SIV_VIAGENS)->group(function () {
            Route::get('/trips', [FleetController::class, 'trips'])->name('fleet.trips');
            Route::post('/trips/{trip}/cancel', [FleetController::class, 'cancelTrip'])->name('fleet.trips.cancel');
        });

        Route::middleware('can:' . P::SIV_VEICULOS)->group(function () {
            Route::get('/vehicles', [FleetController::class, 'vehicles'])->name('fleet.vehicles');
            Route::get('/vehicles/create', [FleetController::class, 'createVehicle'])->name('fleet.vehicles.create');
            Route::post('/vehicles', [FleetController::class, 'storeVehicle'])->name('fleet.vehicles.store');
            Route::get('/vehicles/{vehicle}/edit', [FleetController::class, 'editVehicle'])->name('fleet.vehicles.edit');
            Route::put('/vehicles/{vehicle}', [FleetController::class, 'updateVehicle'])->name('fleet.vehicles.update');
            Route::delete('/vehicles/{vehicle}', [FleetController::class, 'destroyVehicle'])->name('fleet.vehicles.destroy');
        });
    });

    Route::resource('parking-authorizations', ParkingAuthorizationController::class)
        ->middleware('can:' . P::SIV_PLACAS_DIRETORIA);

    // Torneios (módulo novo, docs/funcionalidades/torneios.md). O catálogo de
    // categorias é de cada grupo de espaços (`?group=`).
    // `categories.*` é o catálogo do grupo; `event-categories.*`, as
    // categorias ligadas a um torneio. `{tournament}` só número, para não
    // engolir `categorias` e `novo`.
    Route::prefix('torneios')->as('tournaments.')->group(function () {
        Route::middleware('can:' . P::TORNEIOS_GERENCIAR)->prefix('categorias')->as('categories.')->group(function () {
            Route::get('/', [CategoryTemplateController::class, 'index'])->name('index');
            Route::post('/', [CategoryTemplateController::class, 'store'])->name('store');
            Route::put('/{template}', [CategoryTemplateController::class, 'update'])->name('update');
            Route::delete('/{template}', [CategoryTemplateController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('can:' . P::TORNEIOS_GERENCIAR)->group(function () {
            Route::get('/', [TournamentController::class, 'index'])->name('index');
            Route::get('/novo', [TournamentController::class, 'create'])->name('create');
            Route::post('/', [TournamentController::class, 'store'])->name('store');
            Route::get('/{tournament}', [TournamentController::class, 'show'])->whereNumber('tournament')->name('show');
            Route::get('/{tournament}/editar', [TournamentController::class, 'edit'])->whereNumber('tournament')->name('edit');
            Route::put('/{tournament}', [TournamentController::class, 'update'])->whereNumber('tournament')->name('update');
            Route::put('/{tournament}/situacao', [TournamentController::class, 'status'])->whereNumber('tournament')->name('status');

            Route::post('/{tournament}/categorias', [TournamentCategoryController::class, 'store'])->whereNumber('tournament')->name('event-categories.store');
            Route::put('/{tournament}/categorias/{category}', [TournamentCategoryController::class, 'update'])->whereNumber(['tournament', 'category'])->name('event-categories.update');
            Route::delete('/{tournament}/categorias/{category}', [TournamentCategoryController::class, 'destroy'])->whereNumber(['tournament', 'category'])->name('event-categories.destroy');

            // Chaveamento de uma categoria (F5). A tela (`bracket.show`) fica
            // fora deste grupo: abre também para quem só lança resultado.
            Route::prefix('/{tournament}/categorias/{category}/chaveamento')->whereNumber(['tournament', 'category'])->as('bracket.')->group(function () {
                Route::post('/', [TournamentBracketController::class, 'generate'])->name('generate');
                Route::delete('/', [TournamentBracketController::class, 'destroy'])->name('destroy');
                Route::put('/divulgar', [TournamentBracketController::class, 'publish'])->name('publish');
                Route::put('/jogos/{match}', [TournamentBracketController::class, 'schedule'])->whereNumber('match')->name('schedule');
            });
        });

        Route::middleware('can:' . P::TORNEIOS_INSCRICOES)->group(function () {
            Route::get('/{tournament}/inscricoes', [TournamentRegistrationController::class, 'index'])->whereNumber('tournament')->name('registrations.index');
            Route::post('/{tournament}/inscricoes', [TournamentRegistrationController::class, 'store'])->whereNumber('tournament')->name('registrations.store');
            // O estorno pede também `torneios.pagamentos.estornar` (no controller).
            Route::post('/inscricoes/{registration}/cancelar', [TournamentRegistrationController::class, 'cancel'])->whereNumber('registration')->name('registrations.cancel');

            // Times de uma categoria (F4): o organizador monta, sorteia e ajusta.
            Route::prefix('/{tournament}/categorias/{category}/times')->whereNumber(['tournament', 'category'])->as('teams.')->group(function () {
                Route::get('/', [TournamentTeamController::class, 'index'])->name('index');
                Route::post('/', [TournamentTeamController::class, 'store'])->name('store');
                Route::post('/sortear', [TournamentTeamController::class, 'draw'])->name('draw');
                Route::put('/{team}', [TournamentTeamController::class, 'update'])->whereNumber('team')->name('update');
                Route::delete('/{team}', [TournamentTeamController::class, 'destroy'])->whereNumber('team')->name('destroy');
                Route::post('/{team}/atletas', [TournamentTeamController::class, 'addMember'])->whereNumber('team')->name('members.store');
                Route::delete('/{team}/atletas/{registration}', [TournamentTeamController::class, 'removeMember'])->whereNumber(['team', 'registration'])->name('members.destroy');
            });
        });

        // Tela do chaveamento: `torneios.gerenciar` ou `torneios.resultados` (conferido no controller).
        Route::get('/{tournament}/categorias/{category}/chaveamento', [TournamentBracketController::class, 'show'])
            ->whereNumber(['tournament', 'category'])
            ->name('bracket.show');

        // Resultados (F6): placar, W.O., grupos e o código de lançamento pelo site.
        Route::middleware('can:' . P::TORNEIOS_RESULTADOS)->group(function () {
            Route::prefix('/{tournament}/categorias/{category}')->whereNumber(['tournament', 'category'])->group(function () {
                Route::put('/jogos/{match}/resultado', [TournamentResultController::class, 'record'])->whereNumber('match')->name('results.record');
                Route::delete('/jogos/{match}/resultado', [TournamentResultController::class, 'clear'])->whereNumber('match')->name('results.clear');
                Route::put('/grupos/{group}/fechar', [TournamentResultController::class, 'closeGroup'])->whereNumber('group')->name('results.groups.close');
                Route::delete('/grupos/{group}/fechar', [TournamentResultController::class, 'reopenGroup'])->whereNumber('group')->name('results.groups.reopen');
            });
            Route::post('/{tournament}/codigo-resultados', [TournamentResultController::class, 'generateCode'])->whereNumber('tournament')->name('results.code.store');
            Route::delete('/{tournament}/codigo-resultados', [TournamentResultController::class, 'revokeCode'])->whereNumber('tournament')->name('results.code.destroy');
        });

        // Relatório financeiro (F7).
        Route::middleware('can:' . P::TORNEIOS_FINANCEIRO)->group(function () {
            Route::get('/financeiro', [TournamentFinanceController::class, 'index'])->name('finance.index');
            Route::get('/{tournament}/financeiro', [TournamentFinanceController::class, 'show'])->whereNumber('tournament')->name('finance.show');
            Route::get('/{tournament}/financeiro/csv', [TournamentFinanceController::class, 'csv'])->whereNumber('tournament')->name('finance.csv');
        });

        Route::post('/{tournament}/inscricoes/baixa', [TournamentRegistrationController::class, 'manual'])
            ->whereNumber('tournament')
            ->middleware('can:' . P::TORNEIOS_PAGAMENTOS_BAIXA_MANUAL)
            ->name('registrations.manual');
    });

    // Reservas › Contas do site: contas dos sócios no site de locação
    // (senha provisória, e-mail, cadastro manual, duplicadas). `{member}` é
    // número e inclui as desativadas (resolvido no controller).
    Route::middleware('can:' . P::RESERVAS_CONTAS_SITE)->prefix('contas-site')->as('site-accounts.')->group(function () {
        Route::get('/', [SiteAccountController::class, 'index'])->name('index');
        Route::post('/', [SiteAccountController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/{member}', [SiteAccountController::class, 'show'])->whereNumber('member')->name('show');
        Route::put('/{member}', [SiteAccountController::class, 'update'])->whereNumber('member')->name('update');
        Route::post('/{member}/multiclubes', [SiteAccountController::class, 'pull'])->whereNumber('member')->name('pull');
        Route::post('/{member}/senha', [SiteAccountController::class, 'password'])->whereNumber('member')->middleware('throttle:30,1')->name('password');
        Route::delete('/{member}', [SiteAccountController::class, 'destroy'])->whereNumber('member')->name('destroy');
        Route::post('/{member}/reativar', [SiteAccountController::class, 'restore'])->whereNumber('member')->name('restore');
    });

    // Leitor de documentação (.md) dentro do painel
    Route::get('/docs', [DocumentationController::class, 'index'])->name('docs.index');
    Route::get('/docs/{slug}', [DocumentationController::class, 'show'])
        ->where('slug', '.*')->name('docs.show');

    // Catálogo dos componentes da identidade visual (rebrand). Fora do menu:
    // é a vitrine para conferir botão, cartão e busca nos dois temas, sem
    // depender de uma tela com dado real.
    Route::view('/design/componentes', 'design.components')->name('design.components');

    // Avisos e Lembretes
    // Leitura obrigatória: a tela de ciência e a confirmação. Antes do
    // resource, para "pendentes" não cair em avisos/{aviso}.
    Route::get('avisos/pendentes', [AvisoController::class, 'pending'])->name('avisos.pending');
    Route::post('avisos/{aviso}/ciencia', [AvisoController::class, 'acknowledge'])->name('avisos.acknowledge');
    Route::resource('avisos', AvisoController::class);

    // Freelancers. Cada tela tem a sua permissão, e a ordem importa: as rotas
    // de nome fixo (financeiro, acompanhamento, lotes, validação, diretoria)
    // vêm antes de /freelancer-services/{freelancerService}, que as engoliria.

    // Financeiro dos freelancers.
    Route::group(['middleware' => 'can:' . P::FREELANCERS_FINANCEIRO], function () {
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

    // Acompanhamento do trâmite — tela de leitura.
    Route::group(['middleware' => 'can:' . P::FREELANCERS_ACOMPANHAMENTO], function () {
        Route::get('/freelancer-services/acompanhamento', [FreelancerTrackingController::class, 'index'])
            ->name('freelancer-services.tracking');
    });

    // Lista de serviços — a única tela do módulo de contratos que a
    // Secretaria alcança: sem valores, sem documento, sem ação.
    Route::get('/freelancer-services', [FreelancerServiceWebController::class, 'index'])
        ->middleware('can:' . P::FREELANCERS_SERVICOS_LISTAR)->name('freelancer-services.index');

    Route::prefix('freelancers')->middleware('can:' . P::FREELANCERS_CADASTRO)->group(function () {
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

    Route::prefix('freelancer-functions')->middleware('can:' . P::FREELANCERS_FUNCOES)->group(function () {
        Route::get('/', [FreelancerFunctionController::class, 'index'])->name('freelancer-functions.index');
        Route::get('/create', [FreelancerFunctionController::class, 'create'])->name('freelancer-functions.create');
        Route::post('/', [FreelancerFunctionController::class, 'store'])->name('freelancer-functions.store');
        Route::get('/{freelancerFunction}', [FreelancerFunctionController::class, 'show'])->name('freelancer-functions.show');
        Route::put('/{freelancerFunction}', [FreelancerFunctionController::class, 'update'])->name('freelancer-functions.update');
        Route::delete('/{freelancerFunction}', [FreelancerFunctionController::class, 'destroy'])->name('freelancer-functions.destroy');
    });

    // Serviços / contratos: todo o resto do módulo — valores, documento,
    // assinatura, registro, lotes, validação e diretoria. Validação,
    // aprovação e diretoria ainda exigem o CARGO (Gates de coordenador e
    // checagens no controller), além desta permissão.
    Route::middleware('can:' . P::FREELANCERS_SERVICOS_GERENCIAR)->group(function () {
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

        // Validação dos contratos da redação 2 pelo coordenador do Comercial —
        // um contrato por vez, lido até o fim e confirmado com o PIN. Não há
        // rota que receba vários: a validação é do documento, não de uma lista.
        // Declaradas antes de /freelancer-services/{freelancerService}.
        Route::prefix('freelancer-services/validacao')
            ->middleware('can:validate-freelancer-contracts')
            ->group(function () {
                Route::get('/', [FreelancerValidationController::class, 'index'])->name('freelancer-validation.index');
                Route::get('/{freelancerService}', [FreelancerValidationController::class, 'show'])->name('freelancer-validation.show');
                Route::post('/{freelancerService}', [FreelancerValidationController::class, 'store'])
                    ->middleware('throttle:30,1')->name('freelancer-validation.store');
            });

        // Cadastro da diretoria (nome, e-mail dos códigos e assinatura) — só o
        // coordenador da Gerência. Substitui as variáveis FREELANCER_DIRECTOR_*.
        Route::prefix('freelancer-services/diretoria')
            ->middleware('can:manage-freelancer-director')
            ->group(function () {
                Route::get('/', [FreelancerDirectorController::class, 'edit'])->name('freelancer-director.edit');
                Route::post('/', [FreelancerDirectorController::class, 'update'])->name('freelancer-director.update');
                Route::get('/{director}/assinatura', [FreelancerDirectorController::class, 'signatureImage'])
                    ->name('freelancer-director.signature');
            });

        Route::prefix('freelancer-services')->group(function () {
            Route::get('/create', [FreelancerServiceWebController::class, 'create'])->name('freelancer-services.create');
            // Registro em massa pelo painel (várias linhas, sem planilha) —
            // antes de /{freelancerService} para não cair na rota do serviço.
            Route::get('/em-massa', [FreelancerServiceWebController::class, 'bulkCreate'])->name('freelancer-services.bulk');
            Route::post('/em-massa', [FreelancerServiceWebController::class, 'bulkStore'])->name('freelancer-services.bulk.store');
            // Um código de liberação só para o lote inteiro (dispara e-mail).
            Route::post('/em-massa/weekly-limit-code', [FreelancerServiceWebController::class, 'sendBulkWeeklyLimitCode'])
                ->middleware('throttle:6,1')->name('freelancer-services.bulk.weekly-limit-code');
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
    Route::group(['middleware' => 'can:' . P::CARTEIRINHAS], function () {
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
    Route::prefix('lara')->middleware('can:' . P::LARA)->group(function () {
        Route::get('/', [LaraChatController::class, 'index'])->name('lara.index');
        Route::post('/perguntar', [LaraChatController::class, 'ask'])
            ->middleware('throttle:15,1')->name('lara.ask');
        Route::post('/reiniciar', [LaraChatController::class, 'reset'])
            ->middleware('throttle:15,1')->name('lara.reset');
    });

    // Questor — autorização de Ordem de Compra.
    // As duas rotas de decisão GRAVAM NO ERP DE PRODUÇÃO quando
    // QUESTOR_DRY_RUN está desligado; com ele ligado, as mesmas rotas apenas
    // simulam. O modo é do serviço, não do endereço — assim ligar a gravação
    // não muda a URL nem quebra link salvo.
    // Throttle baixo: cada requisição escreve num ERP externo, e um duplo
    // clique repetido não é um caso que se queira exercitar contra o Questor.
    Route::prefix('questor/ordens-compra')->middleware('can:' . P::COMPRAS)->group(function () {
        Route::get('/', [QuestorPurchaseOrderController::class, 'index'])->name('questor.purchase-orders.index');
        Route::get('/{ordem}', [QuestorPurchaseOrderController::class, 'show'])
            ->where('ordem', '[0-9]+')->name('questor.purchase-orders.show');
        // Uma rota para os três níveis: o serviço decide qual está em jogo a
        // partir do processo e dos cargos de quem clicou. Só a aprovação do
        // terceiro nível chega ao ERP.
        Route::post('/{ordem}/decidir', [QuestorPurchaseOrderController::class, 'decideLevel'])
            ->where('ordem', '[0-9]+')->middleware('throttle:20,1')->name('questor.purchase-orders.decide');
    });

    // Relação centro de custo → diretores: define quem vem SUGERIDO no último
    // nível da aprovação. Mesma permissão da fila — quem edita aqui muda quem
    // costuma decidir o quê.
    Route::prefix('questor/centros-custo')->middleware('can:' . P::COMPRAS)->group(function () {
        Route::get('/', [QuestorCostCenterController::class, 'index'])->name('questor.cost-centers.index');
        Route::put('/{centroCusto}', [QuestorCostCenterController::class, 'update'])
            ->where('centroCusto', '[0-9]+')->name('questor.cost-centers.update');
    });

    /*
    |------------------------------------------------------------------------
    | Mapa de cotação (Questor — SOMENTE LEITURA do ERP)
    |------------------------------------------------------------------------
    |
    | Diferente das rotas de autorização de ordem de compra logo acima: aqui
    | NADA é gravado no Questor, nem com `dry_run` desligado. A solicitação e o
    | histórico de compras são lidos; a cotação inteira vive no banco da Lara.
    |
    | A porta é a permissão `compras`, na rota. O que se faz lá dentro é da
    | policy (App\Policies\CotacaoMapaPolicy): o estado do mapa — fechado é
    | somente leitura — e o cargo (reabrir é do coordenador da Contabilidade)
    | entram na decisão, e o acesso total não passa por cima deles.
    |
    | As rotas fixas vêm ANTES de `/{mapa}`: sem isso, "previa" seria lido como
    | um id de mapa.
    */
    Route::prefix('cotacao/mapas')->name('cotacao.mapas.')->middleware('can:' . P::COMPRAS)->group(function () {
        Route::get('/', [CotacaoMapaController::class, 'index'])->name('index');

        // Consulta o ERP a cada carregamento; o throttle protege o Questor de
        // um F5 insistente na busca de solicitação.
        Route::get('/previa', [CotacaoMapaController::class, 'previa'])
            ->middleware('throttle:60,1')->name('previa');

        Route::get('/fornecedores/buscar', [CotacaoFornecedorController::class, 'buscar'])
            ->middleware('throttle:60,1')->name('fornecedores.buscar');
        Route::get('/fornecedores/condicoes', [CotacaoFornecedorController::class, 'condicoes'])
            ->middleware('throttle:60,1')->name('fornecedores.condicoes');

        Route::post('/', [CotacaoMapaController::class, 'store'])->name('store');

        Route::prefix('/{mapa}')->whereNumber('mapa')->group(function () {
            Route::get('/', [CotacaoMapaController::class, 'show'])->name('show');
            Route::patch('/', [CotacaoMapaController::class, 'update'])->name('update');
            Route::delete('/', [CotacaoMapaController::class, 'destroy'])->name('destroy');

            Route::get('/exportar', [CotacaoMapaController::class, 'exportar'])->name('exportar');
            Route::post('/atualizar-historico', [CotacaoMapaController::class, 'atualizarHistorico'])
                ->name('atualizar-historico');

            // A grade salva por célula, a cada pausa de digitação: o teto é bem
            // mais alto que o das outras rotas de propósito.
            Route::post('/precos', [CotacaoPrecoController::class, 'salvar'])
                ->middleware('throttle:240,1')->name('precos.salvar');

            Route::post('/vencedor', [CotacaoMapaController::class, 'definirVencedor'])->name('vencedor');

            Route::post('/itens', [CotacaoMapaController::class, 'storeItem'])->name('itens.store');
            Route::delete('/itens/{item}', [CotacaoMapaController::class, 'destroyItem'])
                ->whereNumber('item')->name('itens.destroy');
            Route::get('/itens/{item}/historico', [CotacaoMapaController::class, 'historicoItem'])
                ->whereNumber('item')->name('itens.historico');

            Route::post('/fornecedores', [CotacaoFornecedorController::class, 'store'])->name('fornecedores.store');

            // O "salvar geral" das condições: um POST para todas as colunas.
            // Vem ANTES da rota de uma coluna só porque `/fornecedores` sem id
            // não pode ser lido como `/fornecedores/{fornecedor}`.
            Route::patch('/fornecedores', [CotacaoFornecedorController::class, 'atualizarCondicoes'])
                ->name('fornecedores.condicoes-lote');

            Route::patch('/fornecedores/{fornecedor}', [CotacaoFornecedorController::class, 'update'])
                ->whereNumber('fornecedor')->name('fornecedores.update');
            Route::delete('/fornecedores/{fornecedor}', [CotacaoFornecedorController::class, 'destroy'])
                ->whereNumber('fornecedor')->name('fornecedores.destroy');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Bot do WhatsApp — fluxos de conversa
    |----------------------------------------------------------------------
    |
    | Editor dos fluxos que o bot da Lara conduz no lugar do bot da Poli.
    | Salvar um fluxo ativo é publicar: vale na próxima mensagem. O
    | simulador nunca envia nada (App\Services\PoliBot\BotSimulator).
    */
    Route::prefix('bot-whatsapp')->name('poli-bot.')->middleware('can:' . P::BOT_WHATSAPP)->group(function () {
        Route::get('/', [PoliBotFlowController::class, 'index'])->name('index');
        Route::get('/fluxos/novo', [PoliBotFlowController::class, 'create'])->name('flows.create');
        Route::post('/fluxos', [PoliBotFlowController::class, 'store'])->name('flows.store');
        Route::get('/fluxos/{flow}/editar', [PoliBotFlowController::class, 'edit'])->whereNumber('flow')->name('flows.edit');
        Route::put('/fluxos/{flow}', [PoliBotFlowController::class, 'update'])->whereNumber('flow')->name('flows.update');
        Route::post('/fluxos/{flow}/ativo', [PoliBotFlowController::class, 'toggle'])->whereNumber('flow')->name('flows.toggle');
        Route::delete('/fluxos/{flow}', [PoliBotFlowController::class, 'destroy'])->whereNumber('flow')->name('flows.destroy');

        Route::post('/simular', PoliBotSimulatorController::class)->middleware('throttle:120,1')->name('simulate');

        // Dados da conta Poli para as listas do editor (em cache).
        Route::get('/poli/templates', [PoliBotDataController::class, 'templates'])->middleware('throttle:30,1')->name('poli.templates');
        Route::get('/poli/times', [PoliBotDataController::class, 'teams'])->middleware('throttle:30,1')->name('poli.teams');
    });

    // Diário dos atendimentos do O Lara: transferências recebidas e enviadas
    // e encerramentos, com o motivo. Permissão própria — é de quem acompanha
    // o atendimento, não necessariamente de quem edita fluxo.
    Route::get('/bot-whatsapp/ocorrencias', [PoliBotAttendanceEventController::class, 'index'])
        ->middleware('can:' . P::BOT_WHATSAPP_OCORRENCIAS)
        ->name('poli-bot.events.index');

    // Notificações
    Route::get('/notifications/unread-json', [NotificationController::class, 'unreadJson'])->name('notifications.unreadJson');
    Route::get('/notifications/{id}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.markRead');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');

    /*
    |----------------------------------------------------------------------
    | Placar Clube — telas de cadastro e de scout
    |----------------------------------------------------------------------
    |
    | Permissões `placar.cadastro` e `placar.scout` (setor Esporte na matriz
    | inicial). Duas porque cadastro escreve e scout só lê — podem ir para
    | setores diferentes na tela de Setores sem tocar em rota nenhuma.
    */
    Route::prefix('placar')->name('placar.')->group(function () {
        Route::group(['middleware' => 'can:' . P::PLACAR_CADASTRO], function () {
            Route::resource('equipes', PlacarEquipeWebController::class)->except(['edit']);
            Route::post('equipes/{equipe}/logo', [PlacarEquipeWebController::class, 'storeLogo'])->name('equipes.logo.store');
            Route::delete('equipes/{equipe}/logo', [PlacarEquipeWebController::class, 'destroyLogo'])->name('equipes.logo.destroy');

            Route::resource('times', PlacarTimeWebController::class)->except(['edit']);
            Route::post('times/{time}/logo', [PlacarTimeWebController::class, 'storeLogo'])->name('times.logo.store');
            Route::delete('times/{time}/logo', [PlacarTimeWebController::class, 'destroyLogo'])->name('times.logo.destroy');
            Route::post('times/{time}/elenco', [PlacarElencoWebController::class, 'store'])->name('times.elenco.store');
            Route::delete('times/{time}/elenco/{elenco}', [PlacarElencoWebController::class, 'destroy'])->name('times.elenco.destroy');
            Route::post('times/{time}/elenco/copiar', [PlacarElencoWebController::class, 'copiar'])->name('times.elenco.copiar');

            // Declaradas ANTES do resource: `jogadores/import` casaria com
            // `jogadores/{jogador}` e o route-model-binding tentaria achar um
            // jogador chamado "import" — 404 em vez da importação.
            Route::get('jogadores/import/template', [PlacarJogadorWebController::class, 'importTemplate'])->name('jogadores.import.template');
            Route::post('jogadores/import', [PlacarJogadorWebController::class, 'import'])->name('jogadores.import');

            // ->parameters(): Str::singular('jogadores') dá 'jogadore' — o
            // controller espera {jogador} (Jogador $jogador).
            Route::resource('jogadores', PlacarJogadorWebController::class)->except(['edit'])
                ->parameters(['jogadores' => 'jogador']);

            Route::post('jogadores/{jogador}/foto', [PlacarJogadorWebController::class, 'storeFoto'])->name('jogadores.foto.store');
            Route::delete('jogadores/{jogador}/foto', [PlacarJogadorWebController::class, 'destroyFoto'])->name('jogadores.foto.destroy');
            Route::post('jogadores/{jogador}/video', [PlacarJogadorWebController::class, 'storeVideo'])->name('jogadores.video.store');
            Route::delete('jogadores/{jogador}/video', [PlacarJogadorWebController::class, 'destroyVideo'])->name('jogadores.video.destroy');

            // Str::singular('competicoes') dá 'competico' — o controller
            // espera {competicao} (Competicao $competicao).
            Route::resource('competicoes', PlacarCompeticaoWebController::class)->except(['edit'])
                ->parameters(['competicoes' => 'competicao']);

            // /jogos/{jogo}/escalacao antes do resource, senão "escalacao" no
            // lugar de {jogo} seria interpretado como id pela rota de show.
            Route::get('jogos/{jogo}/escalacao', [PlacarEscalacaoWebController::class, 'edit'])->name('jogos.escalacao.edit');
            Route::post('jogos/{jogo}/escalacao', [PlacarEscalacaoWebController::class, 'update'])->name('jogos.escalacao.update');
            Route::resource('jogos', PlacarJogoWebController::class)->except(['edit']);
        });

        Route::group(['middleware' => 'can:' . P::PLACAR_SCOUT], function () {
            Route::get('scout/jogos', [PlacarScoutWebController::class, 'jogos'])->name('scout.jogos');
            Route::get('scout/jogos/{jogo}/sumula', [PlacarScoutWebController::class, 'sumula'])->name('scout.sumula');
            Route::get('scout/jogos/{jogo}/sumula/impressao', [PlacarScoutWebController::class, 'sumulaPrint'])->name('scout.sumula.print');
            Route::get('scout/jogos/{jogo}/jogadores/{jogador}', [PlacarScoutWebController::class, 'atuacao'])->name('scout.atuacao');
            Route::get('scout/jogadores/{jogador}', [PlacarScoutWebController::class, 'jogador'])->name('scout.jogador');
            Route::get('scout/times/{time}', [PlacarScoutWebController::class, 'time'])->name('scout.time');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Replay — vídeos das quadras
    |----------------------------------------------------------------------
    |
    | Permissão do catálogo (`replay`): quem configura são Marketing e TI, e a
    | lista de quem entra muda na tela de Setores sem passar por deploy.
    |
    | As quatro telas são as quatro etapas do mesmo trabalho: definir o
    | formato, desenhar o layout, ligar a câmera e conferir o que foi gravado.
    */
    Route::prefix('replay')->name('replay.')->middleware('can:' . P::REPLAY)->group(function () {
        Route::get('/', fn () => redirect()->route('replay.settings.index'))->name('index');

        Route::get('configuracoes', [ReplaySettingController::class, 'index'])->name('settings.index');
        Route::post('configuracoes', [ReplaySettingController::class, 'store'])->name('settings.store');
        Route::delete('configuracoes/{setting}', [ReplaySettingController::class, 'destroy'])->name('settings.destroy');

        Route::get('layouts', [ReplayLayoutController::class, 'index'])->name('layouts.index');
        Route::get('layouts/create', [ReplayLayoutController::class, 'create'])->name('layouts.create');
        Route::post('layouts', [ReplayLayoutController::class, 'store'])->name('layouts.store');
        Route::get('layouts/{layout}/edit', [ReplayLayoutController::class, 'edit'])->name('layouts.edit');
        Route::put('layouts/{layout}', [ReplayLayoutController::class, 'update'])->name('layouts.update');
        Route::delete('layouts/{layout}', [ReplayLayoutController::class, 'destroy'])->name('layouts.destroy');
        Route::post('layouts/{layout}/logos', [ReplayLayoutController::class, 'storeLogo'])->name('layouts.logos.store');
        // PUT com JSON: é o editor salvando as posições sem recarregar a tela.
        Route::put('layouts/{layout}/itens', [ReplayLayoutController::class, 'updateItems'])->name('layouts.items.update');
        Route::delete('layouts/{layout}/itens/{item}', [ReplayLayoutController::class, 'destroyItem'])->name('layouts.items.destroy');
        Route::post('layouts/{layout}/render', [ReplayLayoutController::class, 'rerender'])->name('layouts.rerender');

        Route::get('cameras', [ReplayCameraController::class, 'index'])->name('cameras.index');
        Route::post('cameras', [ReplayCameraController::class, 'store'])->name('cameras.store');
        Route::put('cameras/{camera}', [ReplayCameraController::class, 'update'])->name('cameras.update');
        Route::delete('cameras/{camera}', [ReplayCameraController::class, 'destroy'])->name('cameras.destroy');

        Route::get('videos', [ReplayVideoWebController::class, 'index'])->name('videos.index');
        Route::delete('videos/{video}', [ReplayVideoWebController::class, 'destroy'])->name('videos.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Assinatura eletrônica presencial — lado do ATENDENTE
    |--------------------------------------------------------------------------
    |
    | O lado do TABLET não está aqui: ele é público, tem sessão própria (cookie
    | `lara_sign`, vinculado a UM documento pela leitura do QR) e mora fora do
    | grupo `auth`, junto das demais rotas públicas. Ver o prefixo
    | `/assinatura/kiosk`.
    |
    | Os modelos de documento são governados por permissão própria: escrever o
    | texto de um termo é ato jurídico, e quem atende no balcão não precisa
    | disso. As rotas de documento pedem a permissão de operação; o recorte
    | fino (editar só rascunho, ver evidência) é da SignatureDocumentPolicy.
    |
    */
    // Guia do usuário do módulo: o passo a passo de quem escreve os modelos e
    // de quem atende. Abre para qualquer um que alcance o módulo.
    Route::prefix('assinatura/guia')->name('signature-guide.')
        ->middleware('can:acessar-guia-assinatura')
        ->group(function () {
            Route::get('/', [SignatureGuideController::class, 'index'])->name('index');
            Route::get('/conteudo', [SignatureGuideController::class, 'content'])->name('content');
            Route::get('/pdf', [SignatureGuideController::class, 'pdf'])->name('pdf');
        });

    // Papel timbrado (cabeçalho e rodapé da empresa). Mesma permissão dos
    // modelos: é a aparência do que as pessoas assinam.
    Route::prefix('assinatura/papel-timbrado')->name('signature-layout.')
        ->middleware('can:' . P::ASSINATURA_MODELOS)
        ->group(function () {
            Route::get('/', [SignatureLayoutController::class, 'edit'])->name('edit');
            Route::post('/', [SignatureLayoutController::class, 'update'])->name('update');
            Route::get('/exemplo', [SignatureLayoutController::class, 'preview'])->name('preview');
        });

    Route::prefix('assinatura/modelos')->name('signature-templates.')
        ->middleware('can:' . P::ASSINATURA_MODELOS)
        ->group(function () {
            Route::get('/', [SignatureTemplateController::class, 'index'])->name('index');
            Route::get('/novo', [SignatureTemplateController::class, 'create'])->name('create');
            Route::post('/', [SignatureTemplateController::class, 'store'])->name('store');
            // Converte o .docx e devolve texto + campos para o formulário; não grava.
            Route::post('/importar-docx', [SignatureTemplateController::class, 'importDocx'])->name('import-docx');

            Route::prefix('/{signatureTemplate}')->whereNumber('signatureTemplate')->group(function () {
                Route::get('/', [SignatureTemplateController::class, 'show'])->name('show');
                Route::get('/revisar', [SignatureTemplateController::class, 'edit'])->name('edit');
                // PUT cria a versão seguinte; não sobrescreve a linha em uso.
                Route::put('/', [SignatureTemplateController::class, 'update'])->name('update');
                Route::delete('/', [SignatureTemplateController::class, 'destroy'])->name('destroy');
            });
        });

    // Assinatura Diretoria: as áreas da diretoria e quem responde por cada
    // uma (titular e substitutos). O diretor escolhido no documento assina no
    // tablet com matrícula e PIN — ver o prefixo público `/assinatura/kiosk/diretoria`.
    Route::prefix('assinatura/diretoria')->name('signature-directors.')
        ->middleware('can:' . P::ASSINATURA_DIRETORIA)
        ->group(function () {
            Route::get('/', [SignatureDirectorAreaController::class, 'index'])->name('index');
            Route::post('/', [SignatureDirectorAreaController::class, 'store'])->name('store');
            Route::put('/{signatureDirectorArea}', [SignatureDirectorAreaController::class, 'update'])
                ->whereNumber('signatureDirectorArea')->name('update');
            Route::post('/{signatureDirectorArea}/mandatos', [SignatureDirectorAreaController::class, 'assign'])
                ->whereNumber('signatureDirectorArea')->name('assign');
            Route::delete('/mandatos/{signatureDirectorAreaHolder}', [SignatureDirectorAreaController::class, 'end'])
                ->whereNumber('signatureDirectorAreaHolder')->name('end');
        });

    // Revisão interna dos documentos assinados: a fila. O registro da revisão
    // é por documento, junto das demais ações dele.
    Route::get('assinatura/revisao', [SignatureReviewController::class, 'index'])
        ->middleware('can:acessar-revisao-assinatura')
        ->name('signature-reviews.index');

    // Termo de Menores (autoatendimento do sócio nos eventos): o termo de
    // cada evento, o pareamento do tablet e o histórico de quem foi
    // autorizado. Cada tela pede a sua permissão; o item do menu, qualquer
    // uma das três. O tablet mora no prefixo público `/assinatura/kiosk/menores`.
    Route::prefix('assinatura/termo-menores')->name('minor-terms.')->group(function () {
        Route::get('/', [MinorTermController::class, 'index'])
            ->middleware('can:acessar-termo-menores')->name('index');

        Route::middleware('can:' . P::ASSINATURA_TERMO_MENORES_GERENCIAR)->group(function () {
            Route::get('/termos', [MinorTermController::class, 'terms'])->name('terms');
            Route::post('/termos', [MinorTermController::class, 'store'])->name('store');
            Route::get('/termos/{signatureMinorTerm}/editar', [MinorTermController::class, 'edit'])
                ->whereNumber('signatureMinorTerm')->name('edit');
            Route::put('/termos/{signatureMinorTerm}', [MinorTermController::class, 'update'])
                ->whereNumber('signatureMinorTerm')->name('update');
        });

        Route::middleware('can:' . P::ASSINATURA_TERMO_MENORES_PAREAR)->group(function () {
            Route::get('/tablets', [MinorTermController::class, 'devices'])->name('devices');
            Route::post('/tablets', [MinorTermController::class, 'startPairing'])
                ->middleware('throttle:20,1')->name('devices.pair');
            // Polling da tela de pareamento: o QR foi lido?
            Route::get('/tablets/{signatureKioskDevice}/status', [MinorTermController::class, 'deviceStatus'])
                ->whereNumber('signatureKioskDevice')->middleware('throttle:120,1')->name('devices.status');
            Route::delete('/tablets/{signatureKioskDevice}', [MinorTermController::class, 'revoke'])
                ->whereNumber('signatureKioskDevice')->name('devices.revoke');
        });

        Route::middleware('can:' . P::ASSINATURA_TERMO_MENORES_HISTORICO)->group(function () {
            Route::get('/historico', [MinorTermController::class, 'history'])->name('history');
            Route::get('/historico/{signatureMinorAuthorization}/foto', [MinorTermController::class, 'photo'])
                ->whereNumber('signatureMinorAuthorization')->name('history.photo');
        });
    });

    Route::prefix('assinatura/documentos')->name('signature-documents.')->group(function () {
        Route::get('/', [SignatureDocumentController::class, 'index'])->name('index');

        // Rotas fixas ANTES de `/{signatureDocument}`: sem isso, "novo" seria
        // lido como id de documento.
        Route::get('/novo', [SignatureDocumentController::class, 'create'])->name('create');
        Route::get('/associados', [SignatureDocumentController::class, 'members'])
            ->middleware('throttle:60,1')->name('members');
        Route::post('/', [SignatureDocumentController::class, 'store'])->name('store');

        // Documento PRONTO, em PDF: aproveitado na íntegra, sem modelo.
        Route::get('/enviar', [SignatureDocumentController::class, 'createUpload'])->name('upload');
        Route::post('/enviar', [SignatureDocumentController::class, 'storeUpload'])
            ->middleware('throttle:20,1')->name('upload.store');

        Route::prefix('/{signatureDocument}')->whereNumber('signatureDocument')->group(function () {
            Route::get('/', [SignatureDocumentController::class, 'show'])->name('show');
            Route::get('/editar', [SignatureDocumentController::class, 'edit'])->name('edit');
            Route::put('/', [SignatureDocumentController::class, 'update'])->name('update');

            Route::post('/congelar', [SignatureDocumentController::class, 'freeze'])
                ->middleware('throttle:20,1')->name('freeze');
            Route::post('/cancelar', [SignatureDocumentController::class, 'cancel'])
                ->middleware('throttle:20,1')->name('cancel');

            Route::get('/pdf', [SignatureDocumentController::class, 'pdf'])->name('pdf');

            // Onde cada pessoa assina no PDF enviado (só no rascunho).
            Route::post('/posicoes', [SignatureDocumentController::class, 'positions'])->name('positions');

            // Acompanhamento da tela do atendente (polling — não há
            // broadcasting neste projeto). Teto alto porque a tela consulta a
            // cada poucos segundos enquanto o atendimento corre.
            Route::get('/status', [SignatureReleaseController::class, 'status'])
                ->middleware('throttle:120,1')->name('status');

            // Gerar o QR de um signatário. A mesma rota regera: "gerar outro"
            // e "gerar o primeiro" são o mesmo ato no balcão, e duas rotas
            // abririam a chance de dois QRs válidos ao mesmo tempo.
            Route::post('/signatarios/{signatureSigner}/liberar', [SignatureReleaseController::class, 'store'])
                ->whereNumber('signatureSigner')
                ->middleware('throttle:30,1')->name('release');

            // Reenvio da via assinada — e-mail falha, e o documento já está
            // guardado: o que falta é a entrega.
            Route::post('/signatarios/{signatureSigner}/via', [SignatureReleaseController::class, 'resend'])
                ->whereNumber('signatureSigner')
                ->middleware('throttle:10,1')->name('resend-copy');

            Route::delete('/liberacoes/{signatureRequest}', [SignatureReleaseController::class, 'destroy'])
                ->whereNumber('signatureRequest')
                ->middleware('throttle:30,1')->name('release.cancel');

            // Assinatura remota: o link por e-mail que abre a tela de quem
            // assina no site externo. Enviar de novo substitui o anterior —
            // ver RemoteSignatureService.
            Route::post('/signatarios/{signatureSigner}/link-remoto', [SignatureRemoteLinkController::class, 'store'])
                ->whereNumber('signatureSigner')
                ->middleware('throttle:10,1')->name('remote-link.store');
            Route::delete('/links-remotos/{signatureRemoteLink}', [SignatureRemoteLinkController::class, 'destroy'])
                ->whereNumber('signatureRemoteLink')
                ->middleware('throttle:30,1')->name('remote-link.destroy');

            // Aba "Assinatura gov.br": preparar o documento para o gov.br e
            // enviar o PDF que voltou assinado. Aprovado, o envio conclui a
            // assinatura de quem assinou — ver GovbrCheckService.
            Route::post('/govbr/preparar', [SignatureGovbrController::class, 'prepare'])
                ->middleware('throttle:20,1')->name('govbr.prepare');
            // Botão "Enviar por e-mail": o PDF a assinar; a resposta volta ao atendente.
            Route::post('/govbr/signatarios/{signatureSigner}/convite', [SignatureGovbrController::class, 'invite'])
                ->whereNumber('signatureSigner')
                ->middleware('throttle:10,1')->name('govbr.invite');
            Route::post('/govbr', [SignatureGovbrController::class, 'store'])
                ->middleware('throttle:20,1')->name('govbr.store');
            Route::get('/govbr/{signatureGovbrCheck}/pdf', [SignatureGovbrController::class, 'pdf'])
                ->whereNumber('signatureGovbrCheck')->name('govbr.pdf');

            // Anexos (identidade, comprovante): o atendente envia na tela do
            // documento. Remover só em rascunho — ver SignatureAttachmentService.
            Route::post('/anexos', [SignatureAttachmentController::class, 'store'])
                ->middleware('throttle:30,1')->name('attachments.store');
            Route::get('/anexos/{signatureAttachment}', [SignatureAttachmentController::class, 'show'])
                ->whereNumber('signatureAttachment')->name('attachments.show');
            Route::delete('/anexos/{signatureAttachment}', [SignatureAttachmentController::class, 'destroy'])
                ->whereNumber('signatureAttachment')
                ->middleware('throttle:30,1')->name('attachments.destroy');

            // Revisão interna (aba "Revisão"): ver SignatureReviewService.
            Route::post('/revisao', [SignatureReviewController::class, 'store'])
                ->middleware('throttle:30,1')->name('review');
        });
    });

});

require __DIR__.'/auth.php';

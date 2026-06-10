<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\DataInfoController;
use App\Http\Controllers\Company\CompanyController;
use App\Http\Controllers\Company\CompanyWorkerController as WorkerController;
use App\Http\Controllers\Company\CompanyAccessRulesController as CompanyRulesController;
use App\Http\Controllers\Tournament\TournamentController;

use App\Http\Controllers\VideoWallController;
use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleRulesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\CompTimeController;
use App\Http\Controllers\ParkingAuthorizationController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeAssistantController;


Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::group(['prefix' => 'parking', 
                'middleware' => 'permission:search parking',
            ], function () {
        Route::get('/search', [ParkingController::class, 'search'])->name('parking.search');
        Route::post('/find', [ParkingController::class, 'show'])->name('parking.show');
    });

    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/accesses/{time}', [AccessController::class, 'findAccessByTime'])->name('accesses.findAccessByTime');
    Route::get('/accesses', [AccessController::class, 'index'])->name('accesses.index');
    
    Route::get('/information', [InformationController::class, 'index'])->name('information.index');
    Route::get('/information/create', [InformationController::class, 'create'])->name('information.create');
    Route::post('/information', [InformationController::class, 'store'])->name('information.store');
    Route::get('/information/{information}', [InformationController::class, 'show'])->name('information.show');
    Route::get('/information/{information}/edit', [InformationController::class, 'edit'])->name('information.edit');
    Route::put('/information/{information}', [InformationController::class, 'update'])->name('information.update');
    Route::delete('/information/{information}', [InformationController::class, 'destroy'])->name('information.destroy');
    
    Route::get('/information/{id}/history', [InformationController::class, 'history'])->name('information.history');
    Route::get('/members/{title}', [MemberController::class, 'findMemberByCode'])->name('information.findMemberByCode');

    Route::group(['prefix' => 'company'], function () {
        Route::get('/', [CompanyController::class, 'index'])->name('company.index');
        Route::get('/create', [CompanyController::class, 'create'])->name('company.create');
        Route::post('/', [CompanyController::class, 'store'])->name('company.store');
        Route::get('/access-monitor', [CompanyRulesController::class, 'monitor'])->name('company.access.monitor');
        Route::get('/access-logs', [CompanyRulesController::class, 'accessLogs'])->name('company.access.logs');
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
        Route::get('/group/{category}/', [PlaceGroupController::class, 'indexByCategory'])->name('api.placegroup.indexByCategory');
        Route::get('/getDates/{place_id?}', [ScheduleRulesController::class, 'getScheduledDates'])->name('schedule.getScheduledDates');
        Route::get('/{id}', [ScheduleController::class, 'show'])->name('schedule.show');
        Route::put('/update', [ScheduleController::class, 'update'])->name('schedule.update');
        Route::post('/store/web', [ScheduleController::class, 'store'])->name('schedule.store.web');
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

});

require __DIR__.'/auth.php';

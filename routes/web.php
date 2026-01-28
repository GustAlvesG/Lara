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

use App\Http\Controllers\VideoWallController;
use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleRulesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\CompTimeController;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

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
        Route::get('/{company}', [CompanyController::class, 'show'])->name('company.show');

        Route::group(['prefix' => '{company}/worker'], function () {
            Route::get('/create', [WorkerController::class, 'create'])->name('company.worker.create');
            Route::post('/', [WorkerController::class, 'store'])->name('company.worker.store');
            Route::delete('/{worker}', [WorkerController::class, 'destroy'])->name('company.worker.destroy');
        });

        Route::group(['prefix' => '{company}/rules'], function () {
            Route::get('/create', [CompanyRulesController::class, 'create'])->name('company.rules.create');
            Route::post('/', [CompanyRulesController::class, 'store'])->name('company.rules.store');
        });
    });
    
    
    Route::get('/videowall', [VideoWallController::class, 'index'])->name('videowall.index');


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
        Route::post('/store/web', [ScheduleController::class, 'storeWeb'])->name('schedule.store.web');
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
            Route::get('/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/{id}', [UserController::class, 'update'])->name('users.update');
        });
        
        
        Route::group(['prefix' => 'roles-permission'], function () {
            Route::get('/', [PermissionController::class, 'index'])->name('roles-permission.index');
        });
    });

    Route::group(['prefix' => 'comp-time'], function () {
        Route::get('/upload', [CompTimeController::class, 'index'])->name('comp-time.index');
        Route::post('/upload', [CompTimeController::class, 'store'])->name('comp-time.store');
        Route::post('/filter', [CompTimeController::class, 'indexFilter'])->name('comp-time.index.filter');
        Route::post('/details', [CompTimeController::class, 'showDetails'])->name('comp-time.show.details');
        Route::post('/details/day', [CompTimeController::class, 'showDayDetails'])->name('comp-time.show.day.details');
        Route::get('/recalculate', [CompTimeController::class, 'recalculateBalances'])->name('comp-time.recalculate');
    });


});

require __DIR__.'/auth.php';

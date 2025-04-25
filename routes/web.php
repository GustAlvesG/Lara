<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ParkingController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AccessController;
use App\Http\Controllers\InformationController;
use App\Http\Controllers\DataInfoController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\OuterController;
use App\Http\Controllers\AccessRuleController;
use App\Http\Controllers\VideoWallController;
use App\Http\Controllers\PlaceGroupController;
use App\Http\Controllers\PlaceController;


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

    Route::get('/parking/search', [ParkingController::class, 'search'])->name('parking.search');
    Route::post('/parking/find', [ParkingController::class, 'show'])->name('parking.show');
    Route::get('/members', [MemberController::class, 'index'])->name('members.index');
    Route::get('/accesses/{time}', [AccessController::class, 'findAccessByTime'])->name('accesses.findAccessByTime');
    Route::get('/accesses', [AccessController::class, 'index'])->name('accesses.index');
    Route::resource('information', InformationController::class);
    Route::get('/information/{id}/history', [InformationController::class, 'history'])->name('information.history');
    Route::get('/members/{title}', [MemberController::class, 'findMemberByCode'])->name('information.findMemberByCode');

    Route::resource('company', CompanyController::class);
    Route::post('company/change/{id}', [CompanyController::class, 'change'])->name('company.change');
    Route::get('/rule/{rule}', [AccessRuleController::class, 'rulesByID'])->name('accessrule.ruleByID');
    Route::get('/rule/create/{company_id}', [AccessRuleController::class, 'create'])->name('accessrule.create');
    Route::get('/rule/create/{outer_id}/outer', [AccessRuleController::class, 'createOuter'])->name('accessrule.createOuter');
    Route::get('/outer/create/{company_id}', [OuterController::class, 'create'])->name('outer.create');
    Route::post('/rule', [AccessRuleController::class, 'store'])->name('accessrule.store');
    Route::post('/outer', [OuterController::class, 'store'])->name('outer.store');
    Route::get('/outer/{id}',[OuterController::class, 'show'])->name('outer.show');
    
    Route::put('/rule/{id}', [AccessRuleController::class, 'update'])->name('accessrule.update');
    Route::delete('/rule', [AccessRuleController::class, 'destroy'])->name('accessrule.destroy');
    
    //Route::resource('outer', OuterController::class);
    

    
    Route::get('/videowall', [VideoWallController::class, 'index'])->name('videowall.index');
    

    Route::resource('place-group', PlaceGroupController::class);

    Route::resource('place', PlaceController::class);

    Route::get('/place-group/{id}/schedule/create', [PlaceGroupController::class, 'createSchedule'])->name('place-group.createSchedule');
    Route::post('/place-group/schedule', [PlaceGroupController::class, 'storeSchedule'])->name('place-group.storeSchedule');
    
});

require __DIR__.'/auth.php';

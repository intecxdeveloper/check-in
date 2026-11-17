<?php

use App\Http\Controllers\Api\CompanySearchController;
use App\Http\Controllers\Api\GeocodeController;
use App\Http\Controllers\Api\GiftSpinController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\GiftController;
use App\Http\Controllers\Staff\VisitorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MapController::class, 'index'])->name('map');
Route::get('/check-in', [MapController::class, 'checkIn'])->name('check-in');

Route::prefix('api')->group(function () {
    Route::get('/map/companies', [MapController::class, 'companies'])->name('api.map.companies');
    Route::get('/map/stats', [MapController::class, 'stats'])->name('api.map.stats');
    Route::get('/map/recent', [MapController::class, 'recent'])->name('api.map.recent');
    Route::get('/services', [MapController::class, 'services'])->name('api.services');
    // Booth Wi‑Fi shares one public IP — keep these generous for live events.
    Route::get('/geocode', [GeocodeController::class, 'search'])
        ->middleware('throttle:90,1')
        ->name('api.geocode');
    Route::get('/geocode/reverse', [GeocodeController::class, 'reverse'])
        ->middleware('throttle:120,1')
        ->name('api.geocode.reverse');
    Route::get('/companies/search', CompanySearchController::class)
        ->middleware('throttle:180,1')
        ->name('api.companies.search');
    Route::post('/check-in', [CheckInController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('api.check-in');
    Route::post('/gift-spin', [GiftSpinController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('api.gift-spin');
});

Route::prefix('staff')->name('staff.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/gifts', [GiftController::class, 'index'])->name('gifts');
        Route::post('/gifts', [GiftController::class, 'store'])->name('gifts.store');
        Route::post('/gifts/starter', [GiftController::class, 'loadStarter'])->name('gifts.starter');
        Route::patch('/gifts/{gift}', [GiftController::class, 'update'])->name('gifts.update');
        Route::delete('/gifts/{gift}', [GiftController::class, 'destroy'])->name('gifts.destroy');
        Route::get('/visitors', [VisitorController::class, 'index'])->name('visitors');
        Route::delete('/visitors/{visitor}', [VisitorController::class, 'destroy'])->name('visitors.destroy');
        Route::delete('/visitors/{visitor}/message', [VisitorController::class, 'destroyMessage'])->name('visitors.message.destroy');
        Route::patch('/leads/{lead}', [VisitorController::class, 'updateLead'])->name('leads.update');
        Route::get('/export', [VisitorController::class, 'export'])->name('export');
    });
});

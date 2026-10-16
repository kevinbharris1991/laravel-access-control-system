<?php

use App\Http\Controllers\AccessControlController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home');

Route::middleware(['throttle:10,1', 'auth.basic'])->group(function () {
    Route::get('/account/access', [AccessControlController::class, 'account']);

    Route::get('/admin/access', [AccessControlController::class, 'catalog'])
        ->middleware(['can:permission:groups.read', 'can:permission:permissions.read']);

    // These demonstrate feature gates only; no domain records are exposed.
    Route::get('/examples/billing', fn () => response()->json(['feature' => 'billing']))
        ->middleware('can:permission:billing.read');

    Route::get('/examples/clinical', fn () => response()->json(['feature' => 'health-profiles']))
        ->middleware('can:permission:health-profiles.read');

    Route::get('/examples/reservations', fn () => response()->json(['feature' => 'own-reservations']))
        ->middleware('can:permission:own-reservations.read');
});

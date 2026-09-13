<?php

use App\Http\Controllers\BpmBridgeController;
use App\Http\Controllers\SharedWidgetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SSO dal BPM esterno. Throttling per limitare il brute force sul token.
Route::get('/bpm-landing/{subject_id}', [BpmBridgeController::class, 'handle'])
    ->middleware('throttle:10,1')
    ->name('bpm.landing');

/*
 * Viste pubbliche condivise: nessuna autenticazione, sola lettura.
 */
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/shared/widgets/{token}', [SharedWidgetController::class, 'show'])
        ->name('shared.widget');

    Route::get('/shared/widgets/{token}/child/{child}', [SharedWidgetController::class, 'child'])
        ->whereNumber('child')
        ->name('shared.widget.child');
});

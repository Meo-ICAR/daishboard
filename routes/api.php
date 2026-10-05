<?php

use App\Http\Controllers\Api\UserLookupApiController;
use Illuminate\Support\Facades\Route;

// Consumato da UnicoBPM per verificare se l'utente loggato ha un account anche qui.
Route::get('/users/lookup', [UserLookupApiController::class, 'show'])->name('api.users.lookup');

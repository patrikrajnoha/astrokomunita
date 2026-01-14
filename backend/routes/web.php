<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/**
 * Optional fallback (poistka) – aby nikdy nepadlo "Route [login] not defined"
 * Ak to nechceš, môžeš zmazať, ale je to bezpečné.
 */
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated'], 401);
})->name('login');

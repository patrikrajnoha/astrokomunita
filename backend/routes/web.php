<?php

use App\Http\Controllers\NewsletterUnsubscribeController;
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

Route::get('/unsubscribe/newsletter/{user}', NewsletterUnsubscribeController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('newsletter.unsubscribe');

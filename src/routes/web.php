<?php

use Illuminate\Support\Facades\Route;
use Equidna\Caronte\Http\Controllers\CaronteController;

//* Caronte
Route::get('/login', [CaronteController::class, 'loginForm'])->name('caronte.login');
Route::post('/login', [CaronteController::class, 'login']);

Route::post('/2fa', [CaronteController::class, 'twoFactorTokenRequest']);
Route::get('/2fa/{token}', [CaronteController::class, 'twoFactorTokenLogin']);

Route::match(['get', 'post'], 'logout', [CaronteController::class, 'logout'])->name('caronte.logout');

Route::get('password/recover', [CaronteController::class, 'passwordRecoverRequestForm']);
Route::post('password/recover', [CaronteController::class, 'passwordRecoverRequest']);
Route::get('password/recover/{token}', [CaronteController::class, 'passwordRecoverTokenValidation']);

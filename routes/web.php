<?php

use App\Http\Controllers\AppController;
use Illuminate\Support\Facades\Route;

// SPA entry
Route::get('/', [AppController::class, 'index']);

// Allow refresh on Vue Router paths
Route::get('/{any}', [AppController::class, 'index'])->where('any', '.*');

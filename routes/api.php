<?php

use App\Http\Controllers\AhpController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\DummyDataController;
use App\Http\Controllers\RiskController;
use Illuminate\Support\Facades\Route;

Route::get('/areas', [AreaController::class, 'index']);

Route::get('/risk/latest', [RiskController::class, 'latest']);
Route::get('/risk/history', [RiskController::class, 'history']);

Route::prefix('ahp')->group(function () {
    Route::get('/criteria', [AhpController::class, 'criteria']);
    Route::get('/pairwise', [AhpController::class, 'pairwise']); // <- tambah ini
    Route::get('/matrix', [AhpController::class, 'matrix']);
    Route::post('/matrix', [AhpController::class, 'saveMatrix']);
    Route::post('/compute', [AhpController::class, 'compute']);
    Route::get('/weights/latest', [AhpController::class, 'latestWeights']);
});

Route::post('/dummy/ingest', [DummyDataController::class, 'ingest']);


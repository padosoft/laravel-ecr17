<?php

use App\Http\Controllers\Ecr17DemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [Ecr17DemoController::class, 'index']);

Route::prefix('ecr17')->group(function () {
    Route::post('connect', [Ecr17DemoController::class, 'connect']);
    Route::post('command/{key}', [Ecr17DemoController::class, 'command']);
    Route::get('logs', [Ecr17DemoController::class, 'logs']);
    Route::post('clear-logs', [Ecr17DemoController::class, 'clearLogs']);
});

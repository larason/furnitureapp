<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Version 1)
|--------------------------------------------------------------------------
|
| The Version 1 API namespace is `/api/v1`. The frozen Group A contract
| (`docs/api/*`, `docs/api/openapi.yaml`) remains the authority for domain
| endpoints. Reserved system routes (e.g. infrastructure health) may live
| here but must never masquerade as business resources.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    })->name('health');
});

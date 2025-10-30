<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\OrganizationApiController;
use App\Http\Controllers\Api\V1\UserApiController;
use App\Http\Controllers\Api\V1\AssessmentApiController;
use App\Http\Controllers\Api\V1\BonusMalusApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// API Version 1 Routes with API Authentication
Route::prefix('v1')->middleware('api.auth')->group(function () {
    
    // Organization endpoints
    Route::prefix('organization')->group(function () {
        Route::get('/', [OrganizationApiController::class, 'show'])
            ->name('api.v1.organization.show');
        Route::get('/statistics', [OrganizationApiController::class, 'statistics'])
            ->name('api.v1.organization.statistics');
        Route::get('/configuration', [OrganizationApiController::class, 'configuration'])
            ->name('api.v1.organization.configuration');
    });

    // Users endpoints
    Route::prefix('users')->group(function () {
        Route::get('/', [UserApiController::class, 'index'])
            ->name('api.v1.users.index');
        Route::get('/{id}', [UserApiController::class, 'show'])
            ->name('api.v1.users.show');
        Route::get('/{id}/competencies', [UserApiController::class, 'competencies'])
            ->name('api.v1.users.competencies');
        Route::get('/{id}/hierarchy', [UserApiController::class, 'hierarchy'])
            ->name('api.v1.users.hierarchy');
    });

    // Assessments endpoints
    Route::prefix('assessments')->group(function () {
        Route::get('/', [AssessmentApiController::class, 'index'])
            ->name('api.v1.assessments.index');
        Route::get('/{id}', [AssessmentApiController::class, 'show'])
            ->name('api.v1.assessments.show');
        Route::get('/{id}/participants', [AssessmentApiController::class, 'participants'])
            ->name('api.v1.assessments.participants');
        Route::get('/{id}/results', [AssessmentApiController::class, 'results'])
            ->name('api.v1.assessments.results');
    });

    // Bonus/Malus endpoints
    Route::prefix('bonus-malus')->group(function () {
        Route::get('/', [BonusMalusApiController::class, 'index'])
            ->name('api.v1.bonus-malus.index');
        Route::get('/configuration', [BonusMalusApiController::class, 'configuration'])
            ->name('api.v1.bonus-malus.configuration');
        Route::get('/{id}', [BonusMalusApiController::class, 'show'])
            ->name('api.v1.bonus-malus.show');
        Route::get('/{id}/results', [BonusMalusApiController::class, 'results'])
            ->name('api.v1.bonus-malus.results');
        Route::get('/users/{userId}/history', [BonusMalusApiController::class, 'userHistory'])
            ->name('api.v1.bonus-malus.user-history');
    });

    // Health check endpoint (useful for monitoring)
    Route::get('/health', function() {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => 'v1'
        ]);
    })->name('api.v1.health');
});

// Public endpoint to check API status (no authentication required)
Route::get('/status', function() {
    return response()->json([
        'api' => 'Quarma360 API',
        'version' => 'v1',
        'status' => 'online',
        'documentation' => url('/api/documentation')
    ]);
})->name('api.status');
<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Public
    Route::get('services', [V1\ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service:slug}', [V1\ServiceController::class, 'show'])->name('services.show');
    Route::get('certificates/verify/{code}', [V1\CertificateVerificationController::class, 'show'])
        ->middleware('throttle:verification')
        ->name('certificates.verify');
    Route::post('auth/tokens', [V1\AuthTokenController::class, 'store'])
        ->middleware('throttle:login')
        ->name('auth.tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::delete('auth/tokens/current', [V1\AuthTokenController::class, 'destroy'])->name('auth.tokens.destroy');
        Route::get('me', V1\MeController::class)->name('me');

        // Citizen
        Route::apiResource('applications', V1\ApplicationController::class)->only(['index', 'store', 'show']);
        Route::get('applications/{application}/certificate', [V1\ApplicationCertificateController::class, 'show'])
            ->name('applications.certificate.show');

        // Back office (ministry employees)
        Route::prefix('backoffice')->name('backoffice.')->middleware('can:access-backoffice')->group(function () {
            Route::apiResource('applications', V1\Backoffice\ApplicationController::class)->only(['index', 'show']);
            Route::post('applications/{application}/approval', [V1\Backoffice\ApplicationApprovalController::class, 'store'])
                ->name('applications.approval.store');
            Route::post('applications/{application}/rejection', [V1\Backoffice\ApplicationRejectionController::class, 'store'])
                ->name('applications.rejection.store');
        });
    });
});

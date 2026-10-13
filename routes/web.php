<?php

use App\Http\Controllers\Web\ApiReferenceController;
use App\Http\Controllers\Web\Auth\CitizenSignInController;
use App\Http\Controllers\Web\Auth\SignOutController;
use App\Http\Controllers\Web\Auth\StaffSignInController;
use App\Http\Controllers\Web\CertificateDownloadController;
use App\Http\Controllers\Web\CertificateVerificationController;
use App\Http\Controllers\Web\Citizen\AccountController;
use App\Http\Controllers\Web\Citizen\ApplicationController as CitizenApplicationController;
use App\Http\Controllers\Web\DocumentationController;
use App\Http\Controllers\Web\LandingController;
use App\Http\Controllers\Web\Staff\ApplicationController as StaffApplicationController;
use App\Http\Controllers\Web\Staff\ApplicationDecisionController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::get('developers', [ApiReferenceController::class, 'show'])->name('developers');
Route::get('developers/openapi.json', [ApiReferenceController::class, 'download'])->name('developers.openapi');

Route::get('verify', [CertificateVerificationController::class, 'index'])->name('verify.index');
Route::get('verify/{code}', [CertificateVerificationController::class, 'show'])
    ->where('code', '[A-Za-z0-9]{1,32}')
    ->middleware('throttle:verification')
    ->name('verify.show');

Route::get('docs/{page}', [DocumentationController::class, 'show'])
    ->whereIn('page', array_keys(DocumentationController::PAGES))
    ->name('docs.show');

Route::middleware('guest')->group(function () {
    // Citizens sign in with Entra External ID, staff with Entra ID.
    Route::inertia('login', 'auth/login')->name('login');
    Route::get('auth/citizen/redirect', [CitizenSignInController::class, 'redirect'])->name('auth.citizen.redirect');
    Route::get('auth/citizen/callback', [CitizenSignInController::class, 'callback'])->name('auth.citizen.callback');
    Route::get('auth/staff/redirect', [StaffSignInController::class, 'redirect'])->name('auth.staff.redirect');
    Route::get('auth/staff/callback', [StaffSignInController::class, 'callback'])->name('auth.staff.callback');
});

Route::post('logout', SignOutController::class)->middleware('auth')->name('logout');

Route::get('applications/{application}/certificate.pdf', CertificateDownloadController::class)->middleware('auth')->name('applications.certificate');

Route::middleware(['auth', 'can:access-citizen-account'])->group(function () {
    Route::get('account', AccountController::class)->name('account');
    Route::get('account/applications/{application}', [CitizenApplicationController::class, 'show'])->name('account.applications.show');
    Route::get('services/{service:slug}/apply', [CitizenApplicationController::class, 'create'])->name('services.apply');
    Route::post('services/{service:slug}/applications', [CitizenApplicationController::class, 'store'])->name('services.applications.store');
});

Route::middleware(['auth', 'can:access-backoffice'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/', [StaffApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/{application}', [StaffApplicationController::class, 'show'])->name('applications.show');
    Route::post('applications/{application}/approval', [ApplicationDecisionController::class, 'approve'])->name('applications.approve');
    Route::post('applications/{application}/rejection', [ApplicationDecisionController::class, 'reject'])->name('applications.reject');
});

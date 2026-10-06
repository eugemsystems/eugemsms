<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Http\Controllers\Public\PublicEnquiryController;

/**
 * BR-PPL-02-001 — the public admissions enquiry form. Unauthenticated, so every route is throttled.
 */
Route::middleware('throttle:10,1')->prefix('apply')->name('people.public.')->group(function (): void {
    Route::get('{slug}', [PublicEnquiryController::class, 'show'])->name('enquiry.show');
    Route::post('{slug}', [PublicEnquiryController::class, 'store'])->name('enquiry.store');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Saas\Livewire\Tenant\Subscription\MySubscription;
use Modules\Saas\Livewire\Vendor\Billing\Invoices as VendorInvoices;
use Modules\Saas\Livewire\Vendor\Licensing\Keys as VendorKeys;
use Modules\Saas\Livewire\Vendor\Subscription\Manage as VendorSubscriptions;
use Modules\Saas\Livewire\Vendor\Subscription\Plans as VendorPlans;

/**
 * School-facing screens: scoped to one school and its own tenant, like
 * every other module's route group.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/account')->name('account.')->group(function (): void {
    Route::livewire('subscription', MySubscription::class)->name('subscription');
});

/**
 * Vendor-facing console (Book J §0.2). A separate realm: its own route
 * group behind `serp.vendor` (vendor identity + IP allowlist + confirmed
 * 2FA), no school prefix, no school context, never listed in the school
 * sidebar, never reachable through a school permission. Components repeat
 * the gate themselves (`AuthorizesVendorConsole`) because Livewire update
 * requests do not re-run this group.
 */
Route::middleware('serp.vendor')->prefix('vendor')->name('vendor.')->group(function (): void {
    Route::livewire('subscriptions', VendorSubscriptions::class)->name('subscriptions');
    Route::livewire('plans', VendorPlans::class)->name('plans');
    Route::livewire('invoices', VendorInvoices::class)->name('invoices');
    Route::livewire('keys', VendorKeys::class)->name('keys');
});

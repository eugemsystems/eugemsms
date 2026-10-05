<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Saas\Domain\Actions\GetPublicStatusAction;
use Modules\Saas\Livewire\Public\Article as HelpArticle;
use Modules\Saas\Livewire\Public\KnowledgeBase as HelpIndex;
use Modules\Saas\Livewire\Tenant\Announcements;
use Modules\Saas\Livewire\Tenant\Subscription\MySubscription;
use Modules\Saas\Livewire\Tenant\Support\Raise as TenantSupportRaise;
use Modules\Saas\Livewire\Vendor\Adoption\Index as VendorAdoption;
use Modules\Saas\Livewire\Vendor\Billing\Invoices as VendorInvoices;
use Modules\Saas\Livewire\Vendor\Broadcasts\Compose as VendorBroadcasts;
use Modules\Saas\Livewire\Vendor\ChurnRisk\Queue as VendorChurn;
use Modules\Saas\Livewire\Vendor\Incidents\Manage as VendorIncidents;
use Modules\Saas\Livewire\Vendor\Licensing\Keys as VendorKeys;
use Modules\Saas\Livewire\Vendor\Onboarding\Index as VendorOnboarding;
use Modules\Saas\Livewire\Vendor\Onboarding\Templates as VendorTemplates;
use Modules\Saas\Livewire\Vendor\Releases\Index as VendorReleases;
use Modules\Saas\Livewire\Vendor\Rollouts\Index as VendorRollouts;
use Modules\Saas\Livewire\Vendor\Subscription\Manage as VendorSubscriptions;
use Modules\Saas\Livewire\Vendor\Subscription\Plans as VendorPlans;
use Modules\Saas\Livewire\Vendor\Support\Queue as VendorSupportQueue;
use Modules\Saas\Livewire\Vendor\Tenants\Index as VendorTenants;
use Modules\Saas\Livewire\Vendor\Tenants\Show as VendorTenantShow;

/**
 * School-facing screens: scoped to one school and its own tenant, like
 * every other module's route group.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/account')->name('account.')->group(function (): void {
    Route::livewire('subscription', MySubscription::class)->name('subscription');
    Route::livewire('announcements', Announcements::class)->name('announcements');
    Route::livewire('support', TenantSupportRaise::class)->name('support');
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

    Route::prefix('tenants')->name('tenants.')->group(function (): void {
        Route::livewire('/', VendorTenants::class)->name('index');
        Route::livewire('{tenant}', VendorTenantShow::class)->whereNumber('tenant')->name('show');
    });

    Route::livewire('rollouts', VendorRollouts::class)->name('rollouts');
    Route::livewire('releases', VendorReleases::class)->name('releases');
    Route::livewire('broadcasts', VendorBroadcasts::class)->name('broadcasts');
    Route::livewire('incidents', VendorIncidents::class)->name('incidents');
    Route::livewire('onboarding', VendorOnboarding::class)->name('onboarding');
    Route::livewire('onboarding/templates', VendorTemplates::class)->name('onboarding.templates');
    Route::livewire('support', VendorSupportQueue::class)->name('support');
    Route::livewire('adoption', VendorAdoption::class)->name('adoption');
    Route::livewire('churn', VendorChurn::class)->name('churn');
});

/**
 * The public incident status page (Book J SAA-02 §4, `GET /status`) — no
 * authentication, public incidents only (`GetPublicStatusAction`).
 */
Route::get('status', fn () => view('saas::public.status', ['incidents' => app(GetPublicStatusAction::class)->execute()]))->name('status');

/** Public help centre (Book J SAA-03 §5) — read-only, no authentication. */
Route::prefix('help')->name('help.')->group(function (): void {
    Route::livewire('/', HelpIndex::class)->name('index');
    Route::livewire('{slug}', HelpArticle::class)->name('article');
});

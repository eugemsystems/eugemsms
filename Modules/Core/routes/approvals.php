<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Approvals\ChainBuilder;
use Modules\Core\Livewire\Approvals\Chains;
use Modules\Core\Livewire\Approvals\Delegations;
use Modules\Core\Livewire\Approvals\MyRequests;
use Modules\Core\Livewire\Approvals\Queue;
use Modules\Core\Livewire\Approvals\Show;
use Modules\Core\Livewire\Approvals\SlaReport;

/**
 * Book A CORE-07 §5 — Workflow & Approvals Engine. Every screen takes
 * an explicit `{school}` and authorises + sets SchoolContext itself
 * (`InteractsWithSchool`), matching schools.php's/roles.php's own
 * convention.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/approvals', Queue::class)->name('approvals.queue');
    Route::livewire('schools/{school}/approvals/mine', MyRequests::class)->name('approvals.mine');
    Route::livewire('schools/{school}/approvals/delegations', Delegations::class)->name('approvals.delegations');
    Route::livewire('schools/{school}/approvals/chains', Chains::class)->name('approvals.chains');
    Route::livewire('schools/{school}/approvals/chains/create', ChainBuilder::class)->name('approvals.chains.create');
    Route::livewire('schools/{school}/approvals/sla-report', SlaReport::class)->name('approvals.sla-report');
    Route::livewire('schools/{school}/approvals/{request}', Show::class)->name('approvals.show');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Finance\Livewire\Billing\History;
use Modules\Finance\Livewire\Billing\Preview;
use Modules\Finance\Livewire\Billing\RunWizard;
use Modules\Finance\Livewire\Fees\AdHocCharge;
use Modules\Finance\Livewire\Fees\Components as FeeComponentsScreen;
use Modules\Finance\Livewire\Fees\LearnerDetail;
use Modules\Finance\Livewire\Fees\Simulator;
use Modules\Finance\Livewire\Fees\StructureBuilder;
use Modules\Finance\Livewire\Fees\Structures;
use Modules\Finance\Livewire\Fees\StructureVersions;

/**
 * Book B FIN-02 §7 — Fee Structure & Billing Engine admin screens.
 * Every route is school-scoped (`{school}`), matching `ledger.php`'s
 * own convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/finance')->name('finance.')->group(function (): void {
    Route::livewire('fees/components', FeeComponentsScreen::class)->name('fees.components');
    Route::livewire('fees/structures', Structures::class)->name('fees.structures');
    Route::livewire('fees/structures/create', StructureBuilder::class)->name('fees.structure-builder.create');
    Route::livewire('fees/structures/{structure}/revise', StructureBuilder::class)->name('fees.structure-builder.revise');
    Route::livewire('fees/structures/{structure}/versions', StructureVersions::class)->name('fees.structure-versions');
    Route::livewire('fees/learners/{student}', LearnerDetail::class)->name('fees.learner-detail');
    Route::livewire('fees/ad-hoc-charge', AdHocCharge::class)->name('fees.ad-hoc-charge');
    Route::livewire('fees/simulator', Simulator::class)->name('fees.simulator');

    Route::livewire('billing/run-wizard', RunWizard::class)->name('billing.run-wizard');
    Route::livewire('billing/runs/{billingRun}', Preview::class)->name('billing.preview');
    Route::livewire('billing/runs', History::class)->name('billing.history');
});

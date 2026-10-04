<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Stores\Livewire\Assets\Depreciation\Run as DepreciationRun;
use Modules\Stores\Livewire\Assets\Disposal\Create as DisposalCreate;
use Modules\Stores\Livewire\Assets\Insurance\Index as InsuranceIndex;
use Modules\Stores\Livewire\Assets\Register\Index as RegisterIndex;
use Modules\Stores\Livewire\Assets\Register\Show as RegisterShow;
use Modules\Stores\Livewire\Assets\Reports\Reconciliation;
use Modules\Stores\Livewire\Assets\Verification\Discrepancies;
use Modules\Stores\Livewire\Assets\Verification\Round as VerificationRound;

/**
 * Book H1 FIN-10 §5 — Fixed Assets & Depreciation admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/stores/assets')->name('stores.assets.')->group(function (): void {
    Route::livewire('register', RegisterIndex::class)->name('register.index');
    Route::livewire('register/{asset}', RegisterShow::class)->name('register.show');
    Route::livewire('depreciation', DepreciationRun::class)->name('depreciation.run');
    Route::livewire('verification', VerificationRound::class)->name('verification.round');
    Route::livewire('verification/discrepancies', Discrepancies::class)->name('verification.discrepancies');
    Route::livewire('disposal', DisposalCreate::class)->name('disposal.create');
    Route::livewire('insurance', InsuranceIndex::class)->name('insurance.index');
    Route::livewire('reports/reconciliation', Reconciliation::class)->name('reports.reconciliation');
});

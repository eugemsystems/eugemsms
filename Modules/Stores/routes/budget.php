<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Stores\Livewire\Budget\Builder\Index as BuilderIndex;
use Modules\Stores\Livewire\Budget\Commitments\Index as CommitmentsIndex;
use Modules\Stores\Livewire\Budget\Consolidation\Review as ConsolidationReview;
use Modules\Stores\Livewire\Budget\Forecast\Index as ForecastIndex;
use Modules\Stores\Livewire\Budget\Variance\Dashboard as VarianceDashboard;
use Modules\Stores\Livewire\Budget\Virement\Create as VirementCreate;

/**
 * Book H1 FIN-11 §5 ⭐ — Budgeting, Forecasting & Commitment Accounting
 * admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/stores/budget')->name('stores.budget.')->group(function (): void {
    Route::livewire('builder', BuilderIndex::class)->name('builder.index');
    Route::livewire('consolidation', ConsolidationReview::class)->name('consolidation.review');
    Route::livewire('variance', VarianceDashboard::class)->name('variance.dashboard');
    Route::livewire('commitments', CommitmentsIndex::class)->name('commitments.index');
    Route::livewire('virement', VirementCreate::class)->name('virement.create');
    Route::livewire('forecast', ForecastIndex::class)->name('forecast.index');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Farm\Livewire\Cycles\Index as CyclesIndex;
use Modules\Farm\Livewire\Fields\Index as FieldsIndex;
use Modules\Farm\Livewire\Harvest\Index as HarvestIndex;
use Modules\Farm\Livewire\KitchenTransfers\Index as KitchenTransfersIndex;
use Modules\Farm\Livewire\Livestock\Index as LivestockIndex;
use Modules\Farm\Livewire\LivestockEvents\Index as LivestockEventsIndex;
use Modules\Farm\Livewire\Production\Index as ProductionIndex;
use Modules\Farm\Livewire\Reports\Index as ReportsIndex;
use Modules\Farm\Livewire\Sales\Index as SalesIndex;
use Modules\Farm\Livewire\Units\Index as UnitsIndex;

/**
 * Book H2 OPS-03 §5 — Estates, Farm & Production Units admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/farm')->name('farm.')->group(function (): void {
    Route::livewire('units', UnitsIndex::class)->name('units.index');
    Route::livewire('fields', FieldsIndex::class)->name('fields.index');
    Route::livewire('cycles', CyclesIndex::class)->name('cycles.index');
    Route::livewire('harvest', HarvestIndex::class)->name('harvest.index');
    Route::livewire('livestock', LivestockIndex::class)->name('livestock.index');
    Route::livewire('livestock-events', LivestockEventsIndex::class)->name('livestock-events.index');
    Route::livewire('production', ProductionIndex::class)->name('production.index');
    Route::livewire('transfers', KitchenTransfersIndex::class)->name('transfers.index');
    Route::livewire('sales', SalesIndex::class)->name('sales.index');
    Route::livewire('reports', ReportsIndex::class)->name('reports.index');
});

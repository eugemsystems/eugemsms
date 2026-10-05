<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Utilities\Livewire\Accounts\Index as AccountsIndex;
use Modules\Utilities\Livewire\Dashboard\Index as DashboardIndex;
use Modules\Utilities\Livewire\GeneratorRuns\Index as GeneratorRunsIndex;
use Modules\Utilities\Livewire\Generators\Index as GeneratorsIndex;
use Modules\Utilities\Livewire\LoadShedding\Index as LoadSheddingIndex;
use Modules\Utilities\Livewire\Meters\Index as MetersIndex;
use Modules\Utilities\Livewire\Readings\Index as ReadingsIndex;
use Modules\Utilities\Livewire\Solar\Index as SolarIndex;
use Modules\Utilities\Livewire\Tokens\Index as TokensIndex;
use Modules\Utilities\Livewire\Water\Index as WaterIndex;

/**
 * Book H2 OPS-04 §6 — Utilities & Energy Management admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/utilities')->name('utilities.')->group(function (): void {
    Route::livewire('accounts', AccountsIndex::class)->name('accounts.index');
    Route::livewire('meters', MetersIndex::class)->name('meters.index');
    Route::livewire('tokens', TokensIndex::class)->name('tokens.index');
    Route::livewire('readings', ReadingsIndex::class)->name('readings.index');
    Route::livewire('generators', GeneratorsIndex::class)->name('generators.index');
    Route::livewire('generator-runs', GeneratorRunsIndex::class)->name('generator-runs.index');
    Route::livewire('solar', SolarIndex::class)->name('solar.index');
    Route::livewire('water', WaterIndex::class)->name('water.index');
    Route::livewire('load-shedding', LoadSheddingIndex::class)->name('load-shedding.index');
    Route::livewire('dashboard', DashboardIndex::class)->name('dashboard.index');
});

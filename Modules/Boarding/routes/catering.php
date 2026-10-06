<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Boarding\Livewire\Catering\Costs;
use Modules\Boarding\Livewire\Catering\Dietary;
use Modules\Boarding\Livewire\Catering\MenuCycles;
use Modules\Boarding\Livewire\Catering\Recipes;
use Modules\Boarding\Livewire\Catering\ServicePlan;
use Modules\Boarding\Livewire\Catering\ServingTerminal;

/**
 * Book F BRD-04 §6 — Catering, Menus & Kitchen admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/boarding')->name('boarding.')->group(function (): void {
    Route::livewire('catering/menu-cycles', MenuCycles::class)->name('catering.menu-cycles');
    Route::livewire('catering/recipes', Recipes::class)->name('catering.recipes');
    Route::livewire('catering/service-plan', ServicePlan::class)->name('catering.service-plan');
    Route::livewire('catering/serving-terminal', ServingTerminal::class)->name('catering.serving-terminal');
    Route::livewire('catering/costs', Costs::class)->name('catering.costs');
    Route::livewire('catering/dietary', Dietary::class)->name('catering.dietary');
});

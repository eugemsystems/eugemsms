<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Boarding\Livewire\Movement\Checkpoints;
use Modules\Boarding\Livewire\Movement\Log as MovementLog;
use Modules\Boarding\Livewire\Occupancy\Live;
use Modules\Boarding\Livewire\RollCall\Board;
use Modules\Boarding\Livewire\RollCall\Escalation;
use Modules\Boarding\Livewire\RollCall\Incident;
use Modules\Boarding\Livewire\RollCall\Incidents;
use Modules\Boarding\Livewire\RollCall\Take;

/**
 * Book F BRD-02 §6 — Roll Call & Movement admin screens ⭐.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/boarding')->name('boarding.')->group(function (): void {
    Route::livewire('roll-call/take', Take::class)->name('rollcall.take');
    Route::livewire('roll-call/board', Board::class)->name('rollcall.board');
    Route::livewire('roll-call/incidents', Incidents::class)->name('rollcall.incidents');
    Route::livewire('roll-call/incidents/{incident}', Incident::class)->name('rollcall.incident');
    Route::livewire('roll-call/escalation', Escalation::class)->name('rollcall.escalation');

    Route::livewire('movement/log', MovementLog::class)->name('movement.log');
    Route::livewire('movement/checkpoints', Checkpoints::class)->name('movement.checkpoints');

    Route::livewire('occupancy/live', Live::class)->name('occupancy.live');
});

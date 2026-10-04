<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Boarding\Livewire\Exeats\Approvals;
use Modules\Boarding\Livewire\Exeats\Index as ExeatsIndex;
use Modules\Boarding\Livewire\Exeats\Overdue;
use Modules\Boarding\Livewire\Exeats\Show as ExeatsShow;
use Modules\Boarding\Livewire\Exeats\Types;
use Modules\Boarding\Livewire\Gate\Attempts;
use Modules\Boarding\Livewire\Gate\Terminal;
use Modules\Boarding\Livewire\Visitors\Blacklist;
use Modules\Boarding\Livewire\Visitors\Log as VisitorsLog;
use Modules\Boarding\Livewire\Visitors\Terminal as VisitorsTerminal;
use Modules\Boarding\Livewire\Visitors\VisitingDays;

/**
 * Book F BRD-03 §6 — Exeat, Leave & Visitor Management admin screens ⭐.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/boarding')->name('boarding.')->group(function (): void {
    Route::livewire('exeats', ExeatsIndex::class)->name('exeats.index');
    Route::livewire('exeats/approvals', Approvals::class)->name('exeats.approvals');
    Route::livewire('exeats/overdue', Overdue::class)->name('exeats.overdue');
    Route::livewire('exeats/types', Types::class)->name('exeats.types');
    Route::livewire('exeats/{exeat}', ExeatsShow::class)->name('exeats.show');

    Route::livewire('gate/terminal', Terminal::class)->name('gate.terminal');
    Route::livewire('gate/attempts', Attempts::class)->name('gate.attempts');

    Route::livewire('visitors/terminal', VisitorsTerminal::class)->name('visitors.terminal');
    Route::livewire('visitors/log', VisitorsLog::class)->name('visitors.log');
    Route::livewire('visitors/blacklist', Blacklist::class)->name('visitors.blacklist');
    Route::livewire('visitors/visiting-days', VisitingDays::class)->name('visitors.visiting-days');
});

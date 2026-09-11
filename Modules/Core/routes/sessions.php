<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Sessions\Calendar;
use Modules\Core\Livewire\Sessions\CloseChecklist;
use Modules\Core\Livewire\Sessions\PeriodControl;
use Modules\Core\Livewire\Sessions\RolloverHistory;
use Modules\Core\Livewire\Sessions\RolloverWizard;
use Modules\Core\Livewire\Sessions\Snapshots;
use Modules\Core\Livewire\Sessions\TermDetail;
use Modules\Core\Livewire\Sessions\TransitionLog;
use Modules\Core\Livewire\Sessions\Years;
use Modules\Core\Livewire\Sessions\YearWizard;

/**
 * Book A CORE-03 §5/§6 — Academic Session & Period Engine. Every screen
 * takes an explicit `{school}` (and `{term}` where relevant) and
 * authorises + sets SchoolContext/SessionContext itself
 * (`InteractsWithSchool`/`InteractsWithSession`), matching schools.php's
 * convention — `serp.web` is deliberately not used here for the same
 * reason schools.php avoids it (see that file's own docblock).
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/sessions/years', Years::class)->name('sessions.years');
    Route::livewire('schools/{school}/sessions/years/create', YearWizard::class)->name('sessions.years.create');
    Route::livewire('schools/{school}/sessions/calendar', Calendar::class)->name('sessions.calendar');
    Route::livewire('schools/{school}/sessions/rollover', RolloverWizard::class)->name('sessions.rollover');
    Route::livewire('schools/{school}/sessions/rollover/history', RolloverHistory::class)->name('sessions.rollover.history');
    Route::livewire('schools/{school}/sessions/snapshots', Snapshots::class)->name('sessions.snapshots');
    Route::livewire('schools/{school}/sessions/transitions', TransitionLog::class)->name('sessions.transitions');
    Route::livewire('schools/{school}/sessions/terms/{term}', TermDetail::class)->name('sessions.terms.show');
    Route::livewire('schools/{school}/sessions/terms/{term}/period/{periodType}', PeriodControl::class)->name('sessions.period');
    Route::livewire('schools/{school}/sessions/terms/{term}/checklist/{periodType}', CloseChecklist::class)->name('sessions.checklist');
});

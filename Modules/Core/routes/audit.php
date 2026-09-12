<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Audit\AccessLog;
use Modules\Core\Livewire\Audit\Explorer;
use Modules\Core\Livewire\Audit\Export;
use Modules\Core\Livewire\Audit\FinancialStream;
use Modules\Core\Livewire\Audit\Integrity;
use Modules\Core\Livewire\Audit\RecordHistory;
use Modules\Core\Livewire\Audit\SecurityEvents;

/**
 * Book A CORE-08 §5 — Audit, Activity & Data Integrity. Every screen
 * takes an explicit `{school}` and authorises + sets SchoolContext
 * itself (`InteractsWithSchool`), matching schools.php's/roles.php's
 * own convention.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools/{school}/audit', Explorer::class)->name('audit.explorer');
    Route::livewire('schools/{school}/audit/history', RecordHistory::class)->name('audit.history');
    Route::livewire('schools/{school}/audit/financial', FinancialStream::class)->name('audit.financial');
    Route::livewire('schools/{school}/audit/security', SecurityEvents::class)->name('audit.security');
    Route::livewire('schools/{school}/audit/access', AccessLog::class)->name('audit.access');
    Route::livewire('schools/{school}/audit/integrity', Integrity::class)->name('audit.integrity');
    Route::livewire('schools/{school}/audit/export', Export::class)->name('audit.export');
});

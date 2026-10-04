<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Welfare\Livewire\Counselling\Diary;
use Modules\Welfare\Livewire\Safeguarding\Audit;
use Modules\Welfare\Livewire\Safeguarding\CaseDetail;
use Modules\Welfare\Livewire\Safeguarding\Cases;
use Modules\Welfare\Livewire\Safeguarding\Grants;
use Modules\Welfare\Livewire\Safeguarding\Report;
use Modules\Welfare\Livewire\Safeguarding\Reviews;
use Modules\Welfare\Livewire\Safeguarding\Triage;
use Modules\Welfare\Livewire\Safeguarding\Vulnerable;

/**
 * Book G BRD-08 §6 — Counselling & Safeguarding admin screens 🔒🔒.
 *
 * Every `{case}` route here binds a `SafeguardingCase` by ulid
 * (`HasUlid`) and is the LAST line of URL-level protection — the real
 * gate is `ViewSafeguardingCaseAction`, called from each screen's own
 * `mount()`, never route middleware.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/welfare')->name('welfare.')->group(function (): void {
    Route::livewire('safeguarding/report', Report::class)->name('safeguarding.report');
    Route::livewire('safeguarding/triage', Triage::class)->name('safeguarding.triage');
    Route::livewire('safeguarding/cases', Cases::class)->name('safeguarding.cases');
    Route::livewire('safeguarding/cases/{case}', CaseDetail::class)->name('safeguarding.case');
    Route::livewire('safeguarding/cases/{case}/grants', Grants::class)->name('safeguarding.grants');
    Route::livewire('safeguarding/vulnerable', Vulnerable::class)->name('safeguarding.vulnerable');
    Route::livewire('safeguarding/reviews', Reviews::class)->name('safeguarding.reviews');
    Route::livewire('safeguarding/audit', Audit::class)->name('safeguarding.audit');

    Route::livewire('counselling/diary', Diary::class)->name('counselling.diary');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Exams\Analysis;
use Modules\Academic\Livewire\Exams\Arrangements;
use Modules\Academic\Livewire\Exams\Candidates;
use Modules\Academic\Livewire\Exams\Invigilation;
use Modules\Academic\Livewire\Exams\Malpractice;
use Modules\Academic\Livewire\Exams\MarkEntry;
use Modules\Academic\Livewire\Exams\Moderate;
use Modules\Academic\Livewire\Exams\Papers;
use Modules\Academic\Livewire\Exams\PaperVault;
use Modules\Academic\Livewire\Exams\Results;
use Modules\Academic\Livewire\Exams\Scripts;
use Modules\Academic\Livewire\Exams\Seating;
use Modules\Academic\Livewire\Exams\Sessions;
use Modules\Academic\Livewire\Exams\Variance;

/**
 * Book E ACA-07 §5 — Examinations Administration admin screens.
 * The candidate-set export to CMP-01 and FIN-02 entry-fee billing are
 * NOT built here — they are Book H3 CMP-01's own job
 * (`DeriveZimsecCandidatesAction`/`BillZimsecEntryFeesAction`,
 * `Modules/Compliance/Livewire/Zimsec/`), consuming the
 * `ExaminationCandidateSet` interface this book only opens. See
 * `.ai/rules/academic.md`.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic')->name('academic.')->group(function (): void {
    Route::livewire('exams/sessions', Sessions::class)->name('exams.sessions');
    Route::livewire('exams/papers', Papers::class)->name('exams.papers');
    Route::livewire('exams/paper-vault', PaperVault::class)->name('exams.paper-vault');
    Route::livewire('exams/candidates', Candidates::class)->name('exams.candidates');
    Route::livewire('exams/seating', Seating::class)->name('exams.seating');
    Route::livewire('exams/invigilation', Invigilation::class)->name('exams.invigilation');
    Route::livewire('exams/scripts', Scripts::class)->name('exams.scripts');
    Route::livewire('exams/mark-entry', MarkEntry::class)->name('exams.mark-entry');
    Route::livewire('exams/variance', Variance::class)->name('exams.variance');
    Route::livewire('exams/moderate', Moderate::class)->name('exams.moderate');
    Route::livewire('exams/arrangements', Arrangements::class)->name('exams.arrangements');
    Route::livewire('exams/malpractice', Malpractice::class)->name('exams.malpractice');
    Route::livewire('exams/results', Results::class)->name('exams.results');
    Route::livewire('exams/analysis', Analysis::class)->name('exams.analysis');
});

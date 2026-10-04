<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Welfare\Livewire\Appeals\Index as AppealsIndex;
use Modules\Welfare\Livewire\Behaviour\Analytics;
use Modules\Welfare\Livewire\Behaviour\Board;
use Modules\Welfare\Livewire\Behaviour\Categories;
use Modules\Welfare\Livewire\Behaviour\Learner;
use Modules\Welfare\Livewire\Behaviour\Record as BehaviourRecord;
use Modules\Welfare\Livewire\Behaviour\Review;
use Modules\Welfare\Livewire\Behaviour\Rules;
use Modules\Welfare\Livewire\Committee\Hearing;
use Modules\Welfare\Livewire\Detentions\Register;
use Modules\Welfare\Livewire\Leadership\Index as LeadershipIndex;
use Modules\Welfare\Livewire\Sanctions\Index as SanctionsIndex;
use Modules\Welfare\Livewire\Sanctions\Issue;
use Modules\Welfare\Livewire\Sanctions\Types;

/**
 * Book G BRD-07 §6 — Discipline, Conduct & Behaviour admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/welfare')->name('welfare.')->group(function (): void {
    Route::livewire('behaviour/categories', Categories::class)->name('behaviour.categories');
    Route::livewire('behaviour/record', BehaviourRecord::class)->name('behaviour.record');
    Route::livewire('behaviour/students/{student}', Learner::class)->name('behaviour.learner');
    Route::livewire('behaviour/board', Board::class)->name('behaviour.board');
    Route::livewire('behaviour/review', Review::class)->name('behaviour.review');
    Route::livewire('behaviour/rules', Rules::class)->name('behaviour.rules');
    Route::livewire('behaviour/analytics', Analytics::class)->name('behaviour.analytics');

    Route::livewire('sanctions/types', Types::class)->name('sanctions.types');
    Route::livewire('sanctions', SanctionsIndex::class)->name('sanctions.index');
    Route::livewire('sanctions/issue', Issue::class)->name('sanctions.issue');

    Route::livewire('detentions', Register::class)->name('detentions.register');
    Route::livewire('committee', Hearing::class)->name('committee.hearing');
    Route::livewire('appeals', AppealsIndex::class)->name('appeals.index');
    Route::livewire('leadership', LeadershipIndex::class)->name('leadership.index');
});

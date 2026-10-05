<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sport\Livewire\Activities\Index as ActivitiesIndex;
use Modules\Sport\Livewire\Awards\Index as AwardsIndex;
use Modules\Sport\Livewire\Equipment\Index as EquipmentIndex;
use Modules\Sport\Livewire\Fixtures\Index as FixturesIndex;
use Modules\Sport\Livewire\Houses\Leaderboard as HousesLeaderboard;
use Modules\Sport\Livewire\Membership\Index as MembershipIndex;
use Modules\Sport\Livewire\Teams\Index as TeamsIndex;

/**
 * Book H2 OPS-07 §4 — Sport, Houses & Co-curricular admin screens.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/sport')->name('sport.')->group(function (): void {
    Route::livewire('activities', ActivitiesIndex::class)->name('activities.index');
    Route::livewire('membership', MembershipIndex::class)->name('membership.index');
    Route::livewire('teams', TeamsIndex::class)->name('teams.index');
    Route::livewire('fixtures', FixturesIndex::class)->name('fixtures.index');
    Route::livewire('houses', HousesLeaderboard::class)->name('houses.index');
    Route::livewire('awards', AwardsIndex::class)->name('awards.index');
    Route::livewire('equipment', EquipmentIndex::class)->name('equipment.index');
});

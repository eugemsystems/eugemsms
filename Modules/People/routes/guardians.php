<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Livewire\Guardians\Create as GuardiansCreate;
use Modules\People\Livewire\Guardians\Duplicates as GuardiansDuplicates;
use Modules\People\Livewire\Guardians\Index as GuardiansIndex;
use Modules\People\Livewire\Guardians\PortalAccess;
use Modules\People\Livewire\Guardians\Show as GuardiansShow;
use Modules\People\Livewire\Guardians\UpdateQueue;
use Modules\People\Livewire\Guardians\Verification;
use Modules\People\Livewire\Households\Index as HouseholdsIndex;
use Modules\People\Livewire\Sponsorships\Index as SponsorshipsIndex;
use Modules\People\Livewire\Sponsorships\Show as SponsorshipsShow;

/**
 * Book C PPL-03 §8 — Guardian directory/profile/create admin screens.
 * School-scoped, matching `students.php`'s own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/people')->name('people.')->group(function (): void {
    Route::livewire('guardians', GuardiansIndex::class)->name('guardians.index');
    Route::livewire('guardians/create', GuardiansCreate::class)->name('guardians.create');
    Route::livewire('guardians/update-queue', UpdateQueue::class)->name('guardians.update-queue');
    Route::livewire('guardians/verification', Verification::class)->name('guardians.verification');
    Route::livewire('guardians/portal-access', PortalAccess::class)->name('guardians.portal-access');
    Route::livewire('guardians/duplicates', GuardiansDuplicates::class)->name('guardians.duplicates');
    Route::livewire('households', HouseholdsIndex::class)->name('households.index');
    Route::livewire('sponsorships', SponsorshipsIndex::class)->name('sponsorships.index');
    Route::livewire('sponsorships/{sponsorship}', SponsorshipsShow::class)->name('sponsorships.show');
    Route::livewire('guardians/{guardian}', GuardiansShow::class)->name('guardians.show');
});

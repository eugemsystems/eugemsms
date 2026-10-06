<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Livewire\Alumni\Campaigns\Index as CampaignsIndex;
use Modules\People\Livewire\Alumni\Directory\Index as DirectoryIndex;
use Modules\People\Livewire\Alumni\Directory\Show as DirectoryShow;
use Modules\People\Livewire\Alumni\Donations\Record as DonationsRecord;
use Modules\People\Livewire\Alumni\Endowments\Index as EndowmentsIndex;
use Modules\People\Livewire\Alumni\Events\Index as EventsIndex;
use Modules\People\Livewire\Alumni\Pledges\Index as PledgesIndex;

/**
 * Book K PPL-06 §5 — Alumni & Institutional Development admin screens.
 * School-scoped, matching the other People route groups.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/alumni')->name('alumni.')->group(function (): void {
    Route::livewire('directory', DirectoryIndex::class)->name('directory.index');
    Route::livewire('directory/{alumnus}', DirectoryShow::class)->whereNumber('alumnus')->name('directory.show');
    Route::livewire('events', EventsIndex::class)->name('events.index');
    Route::livewire('campaigns', CampaignsIndex::class)->name('campaigns.index');
    Route::livewire('pledges', PledgesIndex::class)->name('pledges.index');
    Route::livewire('donations/record', DonationsRecord::class)->name('donations.record');
    Route::livewire('endowments', EndowmentsIndex::class)->name('endowments.index');
});

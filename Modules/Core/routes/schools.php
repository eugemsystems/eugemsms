<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Houses\Index as HousesIndex;
use Modules\Core\Livewire\ModuleEntitlement\Index as ModuleEntitlementIndex;
use Modules\Core\Livewire\Schools\Branding;
use Modules\Core\Livewire\Schools\CloneConfig;
use Modules\Core\Livewire\Schools\Index as SchoolsIndex;
use Modules\Core\Livewire\Schools\Profile;
use Modules\Core\Livewire\Schools\Users;
use Modules\Core\Livewire\Structure\Manager;

/**
 * Book A CORE-02 §5/§6. `schools.index` lists the schools the caller is
 * assigned to and needs no resolved school of its own, so it only needs
 * `auth`/`verified` — every other screen here takes an explicit
 * `{school}` and authorises + sets `SchoolContext` itself
 * (`InteractsWithSchool::loadSchool()`), rather than depending on
 * `serp.web`'s automatic (and, for a specific school by URL, not
 * necessarily correct) context resolution.
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('schools', SchoolsIndex::class)->name('schools.index');
    Route::livewire('schools/{school}/profile', Profile::class)->name('schools.profile');
    Route::livewire('schools/{school}/branding', Branding::class)->name('schools.branding');
    Route::livewire('schools/{school}/users', Users::class)->name('schools.users');
    Route::livewire('schools/{school}/clone', CloneConfig::class)->name('schools.clone');
    Route::livewire('schools/{school}/structure', Manager::class)->name('structure.index');
    Route::livewire('schools/{school}/houses', HousesIndex::class)->name('houses.index');
    Route::livewire('schools/{school}/modules', ModuleEntitlementIndex::class)->name('modules.index');
});

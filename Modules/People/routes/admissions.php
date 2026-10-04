<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Livewire\Admissions\Applications\Convert as ApplicationsConvert;
use Modules\People\Livewire\Admissions\Applications\Create as ApplicationsCreate;
use Modules\People\Livewire\Admissions\Applications\Index as ApplicationsIndex;
use Modules\People\Livewire\Admissions\Applications\Show as ApplicationsShow;
use Modules\People\Livewire\Admissions\Intakes\Index as IntakesIndex;

/**
 * Book C PPL-02 §5 — Admissions & Enrolment CRM admin screens.
 * School-scoped, matching `students.php`'s own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/people')->name('people.')->group(function (): void {
    Route::livewire('admissions/intakes', IntakesIndex::class)->name('admissions.intakes.index');
    Route::livewire('admissions/applications', ApplicationsIndex::class)->name('admissions.applications.index');
    Route::livewire('admissions/applications/create', ApplicationsCreate::class)->name('admissions.applications.create');
    Route::livewire('admissions/applications/{application}', ApplicationsShow::class)->name('admissions.applications.show');
    Route::livewire('admissions/applications/{application}/convert', ApplicationsConvert::class)->name('admissions.applications.convert');
});

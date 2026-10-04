<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Livewire\Guardians\Create as GuardiansCreate;
use Modules\People\Livewire\Guardians\Index as GuardiansIndex;
use Modules\People\Livewire\Guardians\Show as GuardiansShow;

/**
 * Book C PPL-03 §8 — Guardian directory/profile/create admin screens.
 * School-scoped, matching `students.php`'s own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/people')->name('people.')->group(function (): void {
    Route::livewire('guardians', GuardiansIndex::class)->name('guardians.index');
    Route::livewire('guardians/create', GuardiansCreate::class)->name('guardians.create');
    Route::livewire('guardians/{guardian}', GuardiansShow::class)->name('guardians.show');
});

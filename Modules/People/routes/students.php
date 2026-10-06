<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Livewire\Students\ChangeAttribute;
use Modules\People\Livewire\Students\ChangeStatus;
use Modules\People\Livewire\Students\Create as StudentsCreate;
use Modules\People\Livewire\Students\Documents;
use Modules\People\Livewire\Students\Duplicates;
use Modules\People\Livewire\Students\Edit as StudentsEdit;
use Modules\People\Livewire\Students\Guardians as StudentsGuardians;
use Modules\People\Livewire\Students\IdCards;
use Modules\People\Livewire\Students\Index as StudentsIndex;
use Modules\People\Livewire\Students\PriorHistory;
use Modules\People\Livewire\Students\Show as StudentsShow;
use Modules\People\Livewire\Students\Siblings;
use Modules\People\Livewire\Students\Timeline;
use Modules\People\Livewire\Students\TransferOut;

/**
 * Book C PPL-01 §8 — Student Information System admin screens.
 * School-scoped (`{school}`), matching Finance's own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/people')->name('people.')->group(function (): void {
    Route::livewire('students', StudentsIndex::class)->name('students.index');
    Route::livewire('students/create', StudentsCreate::class)->name('students.create');
    Route::livewire('students/duplicates', Duplicates::class)->name('students.duplicates');
    Route::livewire('students/{student}', StudentsShow::class)->name('students.show');
    Route::livewire('students/{student}/edit', StudentsEdit::class)->name('students.edit');
    Route::livewire('students/{student}/change-attribute', ChangeAttribute::class)->name('students.change-attribute');
    Route::livewire('students/{student}/change-status', ChangeStatus::class)->name('students.change-status');
    Route::livewire('students/{student}/guardians', StudentsGuardians::class)->name('students.guardians');
    Route::livewire('students/{student}/documents', Documents::class)->name('students.documents');
    Route::livewire('students/{student}/prior-history', PriorHistory::class)->name('students.prior-history');
    Route::livewire('students/{student}/siblings', Siblings::class)->name('students.siblings');
    Route::livewire('students/{student}/timeline', Timeline::class)->name('students.timeline');
    Route::livewire('students/{student}/transfer-out', TransferOut::class)->name('students.transfer-out');
    Route::livewire('students/{student}/id-card', IdCards::class)->name('students.id-card');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Livewire\Admissions\Applications\Convert as ApplicationsConvert;
use Modules\People\Livewire\Admissions\Applications\Create as ApplicationsCreate;
use Modules\People\Livewire\Admissions\Applications\Documents as ApplicationDocuments;
use Modules\People\Livewire\Admissions\Applications\Index as ApplicationsIndex;
use Modules\People\Livewire\Admissions\Applications\Show as ApplicationsShow;
use Modules\People\Livewire\Admissions\Enquiries\Board as EnquiriesBoard;
use Modules\People\Livewire\Admissions\Exams\Manage as ExamsManage;
use Modules\People\Livewire\Admissions\Intakes\Index as IntakesIndex;
use Modules\People\Livewire\Admissions\Interviews\Schedule as InterviewsSchedule;
use Modules\People\Livewire\Admissions\Reports\Funnel;

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
    Route::livewire('admissions/applications/{application}/documents', ApplicationDocuments::class)->name('admissions.applications.documents');
    Route::livewire('admissions/enquiries', EnquiriesBoard::class)->name('admissions.enquiries');
    Route::livewire('admissions/exams', ExamsManage::class)->name('admissions.exams');
    Route::livewire('admissions/interviews', InterviewsSchedule::class)->name('admissions.interviews');
    Route::livewire('admissions/funnel', Funnel::class)->name('admissions.funnel');
});

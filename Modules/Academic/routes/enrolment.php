<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Allocation\Bulk as AllocationBulk;
use Modules\Academic\Livewire\Allocation\Classes as AllocationClasses;
use Modules\Academic\Livewire\Enrolment\BillingCheck;
use Modules\Academic\Livewire\Enrolment\LearnerSubjects;
use Modules\Academic\Livewire\Groups\Allocate as GroupsAllocate;
use Modules\Academic\Livewire\Groups\Index as GroupsIndex;
use Modules\Academic\Livewire\Selection\Approvals as SelectionApprovals;
use Modules\Academic\Livewire\Selection\Form as SelectionForm;

/**
 * Book D ACA-02 §6 ⭐ — Class, Stream & Subject Enrolment admin
 * screens. School-scoped, matching `people/students.php`'s own route
 * convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic')->name('academic.')->group(function (): void {
    Route::livewire('allocation/classes', AllocationClasses::class)->name('allocation.classes');
    Route::livewire('allocation/bulk', AllocationBulk::class)->name('allocation.bulk');

    Route::livewire('students/{student}/subjects', LearnerSubjects::class)->name('enrolment.subjects');
    Route::livewire('enrolment/billing-check', BillingCheck::class)->name('enrolment.billing-check');

    Route::livewire('groups', GroupsIndex::class)->name('groups.index');
    Route::livewire('groups/allocate', GroupsAllocate::class)->name('groups.allocate');

    Route::livewire('students/{student}/selection', SelectionForm::class)->name('selection.form');
    Route::livewire('selection/approvals', SelectionApprovals::class)->name('selection.approvals');
});

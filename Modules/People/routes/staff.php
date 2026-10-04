<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\People\Livewire\Allocation\TeacherMatrix;
use Modules\People\Livewire\Appraisal\Index as AppraisalIndex;
use Modules\People\Livewire\Appraisal\Show as AppraisalShow;
use Modules\People\Livewire\Duty\Rosters as DutyRosters;
use Modules\People\Livewire\Establishment\Index as EstablishmentIndex;
use Modules\People\Livewire\Leave\Approvals as LeaveApprovals;
use Modules\People\Livewire\Leave\Balances as LeaveBalances;
use Modules\People\Livewire\Leave\Request as LeaveRequest;
use Modules\People\Livewire\Staff\Compliance;
use Modules\People\Livewire\Staff\Contracts;
use Modules\People\Livewire\Staff\Create as StaffCreate;
use Modules\People\Livewire\Staff\Disciplinary;
use Modules\People\Livewire\Staff\ExitProcessing;
use Modules\People\Livewire\Staff\Index as StaffIndex;
use Modules\People\Livewire\Staff\Show as StaffShow;

/**
 * Book C PPL-04 §5 — Staff & Human Resources admin screens.
 * School-scoped, matching `students.php`'s own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/people')->name('people.')->group(function (): void {
    Route::livewire('staff', StaffIndex::class)->name('staff.index');
    Route::livewire('staff/create', StaffCreate::class)->name('staff.create');
    Route::livewire('staff/compliance', Compliance::class)->name('staff.compliance');
    Route::livewire('staff/{staff}', StaffShow::class)->name('staff.show');
    Route::livewire('staff/{staff}/contracts', Contracts::class)->name('staff.contracts');
    Route::livewire('staff/{staff}/disciplinary', Disciplinary::class)->name('staff.disciplinary');
    Route::livewire('staff/{staff}/exit', ExitProcessing::class)->name('staff.exit');

    Route::livewire('establishment', EstablishmentIndex::class)->name('establishment.index');
    Route::livewire('allocation', TeacherMatrix::class)->name('allocation.matrix');

    Route::livewire('leave/request', LeaveRequest::class)->name('leave.request');
    Route::livewire('leave/approvals', LeaveApprovals::class)->name('leave.approvals');
    Route::livewire('leave/balances', LeaveBalances::class)->name('leave.balances');

    Route::livewire('duty/rosters', DutyRosters::class)->name('duty.rosters');

    Route::livewire('appraisal', AppraisalIndex::class)->name('appraisal.index');
    Route::livewire('appraisal/{appraisal}', AppraisalShow::class)->name('appraisal.show');
});

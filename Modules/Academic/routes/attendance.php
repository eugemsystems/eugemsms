<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Attendance\Chronic;
use Modules\Academic\Livewire\Attendance\Compliance;
use Modules\Academic\Livewire\Attendance\Daily;
use Modules\Academic\Livewire\Attendance\Mark;
use Modules\Academic\Livewire\Attendance\ReasonCodes;
use Modules\Academic\Livewire\Attendance\Reports;

/**
 * Book D ACA-04 §6 — Attendance admin screens. School-scoped, matching
 * `people/students.php`'s own route convention.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic')->name('academic.')->group(function (): void {
    Route::livewire('attendance/mark', Mark::class)->name('attendance.mark');
    Route::livewire('attendance/daily', Daily::class)->name('attendance.daily');
    Route::livewire('attendance/compliance', Compliance::class)->name('attendance.compliance');
    Route::livewire('attendance/chronic', Chronic::class)->name('attendance.chronic');
    Route::livewire('attendance/reports', Reports::class)->name('attendance.reports');
    Route::livewire('attendance/reason-codes', ReasonCodes::class)->name('attendance.reason-codes');
});

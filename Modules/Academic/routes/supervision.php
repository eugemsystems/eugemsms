<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Supervision\Coverage;
use Modules\Academic\Livewire\Supervision\LessonPlans;
use Modules\Academic\Livewire\Supervision\Meetings;
use Modules\Academic\Livewire\Supervision\ObservationHistory;
use Modules\Academic\Livewire\Supervision\Observe;
use Modules\Academic\Livewire\Supervision\SchemeOfWork;
use Modules\Academic\Livewire\Supervision\TeacherDashboard;

/**
 * Book K ACA-11 §4 — Teaching Quality, Lesson Planning & Supervision.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic/supervision')->name('academic.supervision.')->group(function (): void {
    Route::livewire('schemes', SchemeOfWork::class)->name('schemes');
    Route::livewire('lesson-plans', LessonPlans::class)->name('lesson-plans');
    Route::livewire('coverage', Coverage::class)->name('coverage');
    Route::livewire('observe', Observe::class)->name('observe');
    Route::livewire('observations', ObservationHistory::class)->name('observations');
    Route::livewire('meetings', Meetings::class)->name('meetings');
    Route::livewire('dashboard', TeacherDashboard::class)->name('dashboard');
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Academic\Livewire\Lms\AssignmentCreate;
use Modules\Academic\Livewire\Lms\CourseSpace;
use Modules\Academic\Livewire\Lms\CourseSpaces;
use Modules\Academic\Livewire\Lms\Discussion;
use Modules\Academic\Livewire\Lms\Marking;
use Modules\Academic\Livewire\Lms\NonSubmission;

/**
 * Book K ACA-08 §5 — Online Assignments & E-Learning (LMS), the teacher and
 * administrator side. Learner access is the API's job (`/api/v1/lms/*`);
 * these screens are school-scoped like every other Academic route group.
 */
Route::middleware(['auth', 'verified'])->prefix('schools/{school}/academic/lms')->name('academic.lms.')->group(function (): void {
    Route::livewire('spaces', CourseSpaces::class)->name('spaces');
    Route::livewire('spaces/{space}', CourseSpace::class)->whereNumber('space')->name('space');
    Route::livewire('spaces/{space}/assignments/create', AssignmentCreate::class)->whereNumber('space')->name('assignment-create');
    Route::livewire('spaces/{space}/discussion', Discussion::class)->whereNumber('space')->name('discussion');
    Route::livewire('assignments/{assignment}/marking', Marking::class)->whereNumber('assignment')->name('marking');
    Route::livewire('assignments/{assignment}/non-submission', NonSubmission::class)->whereNumber('assignment')->name('non-submission');
});

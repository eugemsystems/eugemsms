<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Livewire\Scheduling\DemoData;
use Modules\Core\Livewire\Scheduling\FailedJobs;
use Modules\Core\Livewire\Scheduling\Health;
use Modules\Core\Livewire\Scheduling\Maintenance;
use Modules\Core\Livewire\Scheduling\Progress;
use Modules\Core\Livewire\Scheduling\TaskRuns;
use Modules\Core\Livewire\Scheduling\Tasks;

/**
 * Book A CORE-12 — Jobs, Scheduling & Observability. Tenant-wide, no
 * `{school}` — this data belongs to the whole installation, matching
 * `schools.php`'s/`impersonation.php`'s own convention for that class
 * of screen (see `Tasks`'s own docblock for why).
 */
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('scheduling/tasks', Tasks::class)->name('scheduling.tasks');
    Route::livewire('scheduling/tasks/{taskKey}/runs', TaskRuns::class)->name('scheduling.tasks.runs');
    Route::livewire('scheduling/health', Health::class)->name('scheduling.health');
    Route::livewire('scheduling/failed-jobs', FailedJobs::class)->name('scheduling.failed-jobs');
    Route::livewire('scheduling/progress', Progress::class)->name('scheduling.progress');
    Route::livewire('scheduling/maintenance', Maintenance::class)->name('scheduling.maintenance');
    Route::livewire('scheduling/demo-data', DemoData::class)->name('scheduling.demo-data');
});

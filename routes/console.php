<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Core\Domain\Registry\ScheduledTaskRegistry;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Book A CORE-12 §2/BR-CORE-12-006/007. Every scheduled command is
 * driven from `ScheduledTaskRegistry` — the same "code owns the list"
 * source `scheduled_tasks`/the `Scheduling\Tasks` admin screen already
 * read — rather than a second, hand-kept set of cron lines here that
 * could drift from the registry's own `schedule_expression`. This file
 * runs after every service provider has booted (Laravel defers
 * `routes/console.php` until the console kernel boots), so the
 * registry is already fully populated by the time this executes.
 *
 * `is_per_school` fan-out (a task run once per active school) isn't
 * built at this scheduling layer — the one candidate for it so far
 * (`serp:run-integrity-checks`) loops over active schools inside its
 * own `handle()` instead and is registered with `is_per_school: false`
 * accordingly. A real per-school fan-out here can be added once a
 * second task actually needs it, rather than built speculatively now.
 */
foreach (ScheduledTaskRegistry::all() as $task) {
    if (! $task->isEnabled) {
        continue;
    }

    Schedule::command($task->command)
        ->cron($task->scheduleExpression)
        ->name($task->key)
        ->withoutOverlapping((int) ceil($task->timeoutSeconds / 60));
}

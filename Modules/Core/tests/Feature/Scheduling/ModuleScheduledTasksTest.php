<?php

use Cron\CronExpression;
use Modules\Core\Domain\Registry\ScheduledTaskHandlerRegistry;
use Modules\Core\Domain\Registry\ScheduledTaskRegistry;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\ScheduledTaskRun;
use Modules\Core\Models\School;

it('gives every per-school scheduled task a handler, a valid cron and a runnable command', function (): void {
    expect(ScheduledTaskHandlerRegistry::all())->not->toBeEmpty();

    foreach (ScheduledTaskHandlerRegistry::all() as $key => $handler) {
        $task = ScheduledTaskRegistry::get($key);

        expect($task)->not->toBeNull()
            ->and(CronExpression::isValidExpression($task->scheduleExpression))->toBeTrue("{$key} has an invalid cron")
            ->and($task->command)->toBe("serp:run-task {$key}")
            ->and($task->isPerSchool)->toBeTrue();
    }
});

it('runs every registered per-school task cleanly against an empty school', function (): void {
    School::factory()->create(['status' => 'active']);

    foreach (array_keys(ScheduledTaskHandlerRegistry::all()) as $key) {
        if (str_starts_with($key, 'test.')) {
            continue;
        }

        $this->artisan('serp:run-task', ['key' => $key])->assertSuccessful();

        expect(ScheduledTaskRun::whereHas('task', fn ($q) => $q->where('key', $key))->where('status', 'completed')->exists())->toBeTrue("{$key} did not complete");
    }
});

it('runs a handler once per active school with that school as the context, and skips archived schools', function (): void {
    $seen = [];
    ScheduledTaskHandlerRegistry::register('test.scoped', 'TEST', 'Scoped', '0 * * * *', function (School $school) use (&$seen): string {
        $seen[] = [$school->id, SchoolContext::currentId()];

        return 'ok';
    });

    ScheduledTaskRegistry::syncToDatabase();
    $a = School::factory()->create(['status' => 'active']);
    School::factory()->create(['status' => 'archived']);

    $this->artisan('serp:run-task', ['key' => 'test.scoped'])->assertSuccessful();

    expect($seen)->toContain([$a->id, $a->id])->and(collect($seen)->pluck(0)->unique()->count())->toBe(count($seen));
});

it('keeps going when one school fails and then marks the run failed', function (): void {
    $ran = [];
    ScheduledTaskHandlerRegistry::register('test.failing', 'TEST', 'Failing', '0 * * * *', function (School $school) use (&$ran): string {
        $ran[] = $school->id;

        if (count($ran) === 1) {
            throw new RuntimeException('first school broke');
        }

        return 'ok';
    });

    ScheduledTaskRegistry::syncToDatabase();
    School::factory()->count(2)->create(['status' => 'active']);

    expect(fn () => $this->artisan('serp:run-task', ['key' => 'test.failing'])->run())->toThrow(RuntimeException::class);
    expect(count($ran))->toBeGreaterThanOrEqual(2)
        ->and(ScheduledTaskRun::whereHas('task', fn ($q) => $q->where('key', 'test.failing'))->where('status', 'failed')->exists())->toBeTrue();
});

it('refuses an unknown task key', function (): void {
    $this->artisan('serp:run-task', ['key' => 'no.such.task'])->assertFailed();
});

it('runs every platform-level scheduled task cleanly', function (): void {
    $keys = collect(ScheduledTaskRegistry::all())
        ->filter(fn ($task): bool => ! $task->isPerSchool && str_starts_with($task->command, 'serp:run-task '))
        ->keys();

    expect($keys)->not->toBeEmpty();

    foreach ($keys as $key) {
        $this->artisan('serp:run-task', ['key' => $key])->assertSuccessful();
    }
});

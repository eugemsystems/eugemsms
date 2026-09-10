<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Scheduling\CancelJobProgressAction;
use Modules\Core\Domain\Actions\Scheduling\CompleteJobProgressAction;
use Modules\Core\Domain\Actions\Scheduling\FailJobProgressAction;
use Modules\Core\Domain\Actions\Scheduling\StartJobProgressAction;
use Modules\Core\Domain\Actions\Scheduling\UpdateJobProgressAction;
use Modules\Core\Domain\DataObjects\Scheduling\CancelJobProgressData;
use Modules\Core\Domain\DataObjects\Scheduling\CompleteJobProgressData;
use Modules\Core\Domain\DataObjects\Scheduling\FailJobProgressData;
use Modules\Core\Domain\DataObjects\Scheduling\StartJobProgressData;
use Modules\Core\Domain\DataObjects\Scheduling\UpdateJobProgressData;
use Modules\Core\Models\School;

it('starts a job progress record with a ulid and a running status (BR-CORE-12-005)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $progress = app(StartJobProgressAction::class)->execute(new StartJobProgressData(
        schoolId: $school->id,
        userId: $user->id,
        jobType: 'report_card_batch',
        title: 'Generating 1,400 report cards',
        totalSteps: 1400,
    ));

    expect($progress->status)->toBe('running')
        ->and($progress->ulid)->not->toBeEmpty()
        ->and($progress->total_steps)->toBe(1400)
        ->and($progress->completed_steps)->toBe(0);
});

it('advances live counts and the current step message the UI polls (AC-CORE-12-004)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $progress = app(StartJobProgressAction::class)->execute(new StartJobProgressData(
        schoolId: $school->id,
        userId: $user->id,
        jobType: 'report_card_batch',
        title: 'Generating report cards',
        totalSteps: 100,
    ));

    $updated = app(UpdateJobProgressAction::class)->execute(new UpdateJobProgressData(
        jobProgressId: $progress->id,
        completedSteps: 40,
        currentMessage: 'Learner 40 of 100',
    ));

    expect($updated->completed_steps)->toBe(40)
        ->and($updated->current_message)->toBe('Learner 40 of 100')
        ->and($updated->percentComplete())->toBe(40.0);
});

it('completes a job progress record with a result payload', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $progress = app(StartJobProgressAction::class)->execute(new StartJobProgressData(
        schoolId: $school->id,
        userId: $user->id,
        jobType: 'report_card_batch',
        title: 'Generating report cards',
        totalSteps: 10,
    ));

    $completed = app(CompleteJobProgressAction::class)->execute(new CompleteJobProgressData(
        jobProgressId: $progress->id,
        result: ['file_id' => 42],
    ));

    expect($completed->status)->toBe('completed')
        ->and($completed->completed_steps)->toBe(10)
        ->and($completed->result)->toBe(['file_id' => 42])
        ->and($completed->completed_at)->not->toBeNull();
});

it('fails a job progress record with an error message', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $progress = app(StartJobProgressAction::class)->execute(new StartJobProgressData(
        schoolId: $school->id,
        userId: $user->id,
        jobType: 'report_card_batch',
        title: 'Generating report cards',
    ));

    $failed = app(FailJobProgressAction::class)->execute(new FailJobProgressData(
        jobProgressId: $progress->id,
        error: 'Template not found.',
    ));

    expect($failed->status)->toBe('failed')
        ->and($failed->error)->toBe('Template not found.');
});

it('cancels a job progress record', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $progress = app(StartJobProgressAction::class)->execute(new StartJobProgressData(
        schoolId: $school->id,
        userId: $user->id,
        jobType: 'report_card_batch',
        title: 'Generating report cards',
    ));

    $cancelled = app(CancelJobProgressAction::class)->execute(new CancelJobProgressData(
        jobProgressId: $progress->id,
    ));

    expect($cancelled->status)->toBe('cancelled');
});

it('returns null percent complete when total_steps is unknown', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();

    $progress = app(StartJobProgressAction::class)->execute(new StartJobProgressData(
        schoolId: $school->id,
        userId: $user->id,
        jobType: 'unbounded_task',
        title: 'Streaming export',
    ));

    expect($progress->percentComplete())->toBeNull();
});

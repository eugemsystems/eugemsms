<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Documents\GenerateGapReportAction;
use Modules\Core\Domain\Actions\Documents\UpdateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Documents\VoidAllocatedNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Documents\GapReportData;
use Modules\Core\Domain\DataObjects\Documents\UpdateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Documents\VoidAllocatedNumberData;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Domain\Exceptions\ReasonRequiredException;
use Modules\Core\Domain\Exceptions\SeriesPatternLockedException;
use Modules\Core\Models\AllocatedNumber;
use Modules\Core\Models\School;

it('creates a numbering series', function (): void {
    $school = School::factory()->create(['code' => 'ESC']);

    $series = app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id,
        documentType: 'receipt',
        pattern: '{SCHOOL}/{TYPE}/{SEQ:6}',
    ));

    expect($series->next_sequence)->toBe(1)
        ->and($series->reset_policy)->toBe('never');
});

it('refuses to create a duplicate series for the same period (BR-CORE-06-003)', function (): void {
    $school = School::factory()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));
})->throws(DuplicateRecordException::class);

it('allocates sequential, gapless, uniquely formatted numbers (BR-CORE-06-001/003/AC-CORE-06-001)', function (): void {
    $school = School::factory()->create(['code' => 'ESC']);
    $user = User::factory()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SCHOOL}/{TYPE}/{SEQ:6}'));

    $numbers = [];

    for ($i = 0; $i < 10; $i++) {
        $numbers[] = app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));
    }

    $sequences = array_map(fn (AllocatedNumber $n): int => $n->sequence, $numbers);
    $formatted = array_map(fn (AllocatedNumber $n): string => $n->formatted_number, $numbers);

    expect($sequences)->toBe(range(1, 10))
        ->and(array_unique($formatted))->toHaveCount(10)
        ->and($formatted[0])->toBe('ESC/RECEIPT/000001');
});

it('never reuses a voided number and requires a reason (BR-CORE-06-002)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));

    $first = app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));
    app(VoidAllocatedNumberAction::class)->execute(new VoidAllocatedNumberData($first->id, 'Duplicate entry, cancelled.', $user->id));

    $second = app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));

    expect($first->fresh()->isVoided())->toBeTrue()
        ->and($second->sequence)->toBe(2)
        ->and($second->formatted_number)->not->toBe($first->formatted_number);
});

it('refuses to void without a reason', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));
    $number = app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));

    app(VoidAllocatedNumberAction::class)->execute(new VoidAllocatedNumberData($number->id, '', $user->id));
})->throws(ReasonRequiredException::class);

it('locks the pattern once numbers have been allocated this period (BR-CORE-06-005)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $series = app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));
    app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));

    app(UpdateNumberingSeriesAction::class)->execute(new UpdateNumberingSeriesData($series->id, pattern: '{TYPE}/{SEQ:6}'));
})->throws(SeriesPatternLockedException::class);

it('allows non-pattern changes even after numbers are allocated', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $series = app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));
    app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));

    $updated = app(UpdateNumberingSeriesAction::class)->execute(new UpdateNumberingSeriesData($series->id, isActive: false));

    expect($updated->is_active)->toBeFalse();
});

it('reports every voided number with its reason (BR-CORE-06-006)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData($school->id, 'receipt', '{SEQ:6}'));

    $a = app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));
    app(AllocateNumberAction::class)->execute(new AllocateNumberData($school->id, 'receipt', $user->id));
    app(VoidAllocatedNumberAction::class)->execute(new VoidAllocatedNumberData($a->id, 'Wrong amount entered.', $user->id));

    $report = app(GenerateGapReportAction::class)->execute(new GapReportData($school->id));

    expect($report)->toHaveCount(1)
        ->and($report[0]->reason)->toBe('Wrong amount entered.');
});

<?php

use Modules\Core\Domain\Actions\Schools\UpdateSchoolProfileAction;
use Modules\Core\Domain\Contracts\Schools\SchoolLifecycleGuard;
use Modules\Core\Domain\DataObjects\Schools\UpdateSchoolData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Models\School;

it('updates the fields given and leaves the rest untouched', function (): void {
    $school = School::factory()->create(['name' => 'Old Name', 'province' => 'Harare']);

    $updated = app(UpdateSchoolProfileAction::class)->execute(new UpdateSchoolData(
        schoolId: $school->id,
        name: 'New Name',
    ));

    expect($updated->name)->toBe('New Name')
        ->and($updated->province)->toBe('Harare');
});

it('changes the base currency when no financial activity has been recorded', function (): void {
    $school = School::factory()->create(['base_currency' => 'USD']);

    $updated = app(UpdateSchoolProfileAction::class)->execute(new UpdateSchoolData(
        schoolId: $school->id,
        baseCurrency: 'ZWG',
    ));

    expect($updated->base_currency)->toBe('ZWG');
});

it('rejects a base currency change once financial activity is recorded (BR-CORE-02-010)', function (): void {
    $school = School::factory()->create(['base_currency' => 'USD']);

    $guard = Mockery::mock(SchoolLifecycleGuard::class);
    $guard->shouldReceive('hasFinancialActivity')->andReturn(true);
    app()->instance(SchoolLifecycleGuard::class, $guard);

    app(UpdateSchoolProfileAction::class)->execute(new UpdateSchoolData(
        schoolId: $school->id,
        baseCurrency: 'ZWG',
    ));
})->throws(InvalidStateTransitionException::class);

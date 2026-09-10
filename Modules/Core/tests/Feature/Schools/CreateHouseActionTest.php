<?php

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Schools\CreateHouseAction;
use Modules\Core\Domain\DataObjects\Schools\CreateHouseData;
use Modules\Core\Models\School;

it('creates a house for a school', function (): void {
    $school = School::factory()->create();

    $house = (new CreateHouseAction)->execute(new CreateHouseData(
        schoolId: $school->id,
        code: 'CHI',
        name: 'Chitepo',
        colour: '#ff0000',
    ));

    expect($house->exists)->toBeTrue()
        ->and($house->school_id)->toBe($school->id)
        ->and($house->colour)->toBe('#ff0000');
});

it('rejects a duplicate house code within the same school', function (): void {
    $school = School::factory()->create();

    (new CreateHouseAction)->execute(new CreateHouseData($school->id, 'DUP', 'A'));
    (new CreateHouseAction)->execute(new CreateHouseData($school->id, 'DUP', 'B'));
})->throws(ValidationException::class);

it('rejects a malformed colour', function (): void {
    $school = School::factory()->create();

    (new CreateHouseAction)->execute(new CreateHouseData($school->id, 'X', 'X', colour: 'not-a-colour'));
})->throws(ValidationException::class);

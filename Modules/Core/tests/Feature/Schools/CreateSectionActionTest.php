<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Schools\CreateSectionAction;
use Modules\Core\Domain\DataObjects\Schools\CreateSectionData;
use Modules\Core\Domain\Events\Schools\SectionCreated;
use Modules\Core\Models\School;

it('creates a section for a school', function (): void {
    // Faked selectively: a blanket Event::fake() would also swallow the
    // Eloquent `creating` event HasUlid listens on, for the very
    // SchoolSection this action is about to create.
    Event::fake([SectionCreated::class]);

    $school = School::factory()->create();

    $section = (new CreateSectionAction)->execute(new CreateSectionData(
        schoolId: $school->id,
        code: 'JUN',
        name: 'Junior School',
        type: 'primary',
    ));

    expect($section->exists)->toBeTrue()
        ->and($section->school_id)->toBe($school->id);
    Event::assertDispatched(SectionCreated::class);
});

it('rejects a duplicate section code within the same school', function (): void {
    $school = School::factory()->create();

    (new CreateSectionAction)->execute(new CreateSectionData($school->id, 'DUP', 'A', 'primary'));
    (new CreateSectionAction)->execute(new CreateSectionData($school->id, 'DUP', 'B', 'primary'));
})->throws(ValidationException::class);

it('allows the same section code across two different schools', function (): void {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();

    $sectionA = (new CreateSectionAction)->execute(new CreateSectionData($schoolA->id, 'SAME', 'A', 'primary'));
    $sectionB = (new CreateSectionAction)->execute(new CreateSectionData($schoolB->id, 'SAME', 'B', 'primary'));

    expect($sectionA->code)->toBe('SAME')->and($sectionB->code)->toBe('SAME');
});

it('rejects an invalid section type', function (): void {
    $school = School::factory()->create();

    (new CreateSectionAction)->execute(new CreateSectionData($school->id, 'X', 'X', 'not-a-type'));
})->throws(ValidationException::class);

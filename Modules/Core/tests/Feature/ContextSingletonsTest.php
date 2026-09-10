<?php

use Modules\Core\Domain\Exceptions\MissingSchoolContextException;
use Modules\Core\Domain\Exceptions\MissingSessionContextException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

it('has no active school by default', function (): void {
    expect(SchoolContext::current())->toBeNull()
        ->and(SchoolContext::currentId())->toBeNull();
});

it('throws when asserting an unset school context', function (): void {
    SchoolContext::assertSet();
})->throws(MissingSchoolContextException::class);

it('sets and clears the school context', function (): void {
    $school = School::factory()->create();

    SchoolContext::set($school);
    expect(SchoolContext::currentId())->toBe($school->id);

    SchoolContext::clear();
    expect(SchoolContext::current())->toBeNull();
});

it('throws when reading the year of an unset session context', function (): void {
    SessionContext::year();
})->throws(MissingSessionContextException::class);

it('reports isCurrentLiveTerm based on the term\'s is_current flag', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $liveTerm = Term::factory()->current()->for($school)->for($year, 'academicYear')->create(['number' => 1, 'name' => 'Term 1']);
    $historicalTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 2, 'name' => 'Term 2']);

    SessionContext::set($year, $liveTerm);
    expect(SessionContext::isCurrentLiveTerm())->toBeTrue();

    SessionContext::set($year, $historicalTerm);
    expect(SessionContext::isCurrentLiveTerm())->toBeFalse();

    SessionContext::set($year, null);
    expect(SessionContext::isCurrentLiveTerm())->toBeFalse()
        ->and(SessionContext::term())->toBeNull()
        ->and(SessionContext::termId())->toBeNull()
        ->and(SessionContext::yearId())->toBe($year->id);
});

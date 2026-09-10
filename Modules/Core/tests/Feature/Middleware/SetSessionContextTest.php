<?php

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\MissingSessionContextException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Http\Middleware\SetSessionContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Core\Models\UserSessionPreference;

it('passes through when no school context is set yet', function (): void {
    $response = (new SetSessionContext)->handle(Request::create('/'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok')
        ->and(SessionContext::isSet())->toBeFalse();
});

it('falls back to the school\'s current year and term', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->current()->for($school)->create();
    $term = Term::factory()->current()->for($school)->for($year, 'academicYear')->create();

    SchoolContext::set($school);

    (new SetSessionContext)->handle(Request::create('/'), fn () => response('ok'));

    expect(SessionContext::yearId())->toBe($year->id)
        ->and(SessionContext::termId())->toBe($term->id);
});

it('honours a stored user session preference over the school default', function (): void {
    $school = School::factory()->create();
    $defaultYear = AcademicYear::factory()->current()->for($school)->create(['name' => '2026']);
    Term::factory()->current()->for($school)->for($defaultYear, 'academicYear')->create();

    $preferredYear = AcademicYear::factory()->for($school)->create(['name' => '2025']);
    $preferredTerm = Term::factory()->for($school)->for($preferredYear, 'academicYear')->create();

    $user = User::factory()->create();
    UserSessionPreference::create([
        'user_id' => $user->id,
        'school_id' => $school->id,
        'academic_year_id' => $preferredYear->id,
        'term_id' => $preferredTerm->id,
    ]);

    SchoolContext::set($school);
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);

    (new SetSessionContext)->handle($request, fn () => response('ok'));

    expect(SessionContext::yearId())->toBe($preferredYear->id)
        ->and(SessionContext::termId())->toBe($preferredTerm->id);
});

it('rejects the request when the school has no active academic year', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);

    (new SetSessionContext)->handle(Request::create('/'), fn () => response('ok'));
})->throws(MissingSessionContextException::class);

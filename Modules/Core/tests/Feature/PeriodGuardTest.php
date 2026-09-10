<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\BelongsToSession;
use Modules\Core\Domain\Exceptions\PeriodLockedException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

function periodGuardProbe(): Model
{
    return new class extends Model
    {
        use BelongsToSchool;
        use BelongsToSession;

        protected $table = 'period_guard_probes';

        protected $guarded = [];
    };
}

beforeEach(function (): void {
    Schema::create('period_guard_probes', function ($table): void {
        $table->id();
        $table->foreignId('school_id');
        $table->foreignId('academic_year_id');
        $table->foreignId('term_id')->nullable();
        $table->string('label')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('period_guard_probes');
});

it('allows writes while the term is open', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

    SchoolContext::set($school);
    SessionContext::set($year, $term);

    $probe = periodGuardProbe();
    $probe->label = 'ok';
    $probe->save();

    expect($probe->wasRecentlyCreated)->toBeTrue()
        ->and($probe->term_id)->toBe($term->id)
        ->and($probe->academic_year_id)->toBe($year->id);
});

it('blocks writes once the term is locked', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->locked()->for($school)->for($year, 'academicYear')->create();

    SchoolContext::set($school);
    SessionContext::set($year, $term);

    $probe = periodGuardProbe();
    $probe->label = 'blocked';
    $probe->save();
})->throws(PeriodLockedException::class);

it('blocks writes in a soft-closed term without an approved override', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->softClosed()->for($school)->for($year, 'academicYear')->create();

    SchoolContext::set($school);
    SessionContext::set($year, $term);

    $probe = periodGuardProbe();
    $probe->label = 'blocked';
    $probe->save();
})->throws(PeriodLockedException::class);

it('allows writes in a soft-closed term with an approved override', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->softClosed()->for($school)->for($year, 'academicYear')->create();

    SchoolContext::set($school);
    SessionContext::set($year, $term);

    $probe = periodGuardProbe();
    $probe->label = 'override';
    $probe->periodOverrideApproved = true;
    $probe->save();

    expect($probe->wasRecentlyCreated)->toBeTrue();
});

it('never blocks reads of a record even once its term is locked', function (): void {
    $school = School::factory()->create();
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();

    SchoolContext::set($school);
    SessionContext::set($year, $term);

    $probe = periodGuardProbe();
    $probe->label = 'read-me';
    $probe->save();
    $probeId = $probe->id;

    $term->forceFill(['academic_state' => 'locked', 'financial_state' => 'locked'])->saveQuietly();
    SessionContext::set($year, $term->refresh());

    expect(periodGuardProbe()::query()->find($probeId))->not->toBeNull();
});

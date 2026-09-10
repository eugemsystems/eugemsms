<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Core\Models\Term;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Domain\Actions\CreateFeeLiabilityAction;
use Modules\People\Domain\Actions\CreateGuardianAction;
use Modules\People\Domain\Actions\CreateStudentAction;
use Modules\People\Domain\Actions\DeactivateStudentGuardianAction;
use Modules\People\Domain\Actions\LinkGuardianToStudentAction;
use Modules\People\Domain\DataObjects\CreateFeeLiabilityData;
use Modules\People\Domain\DataObjects\CreateGuardianData;
use Modules\People\Domain\DataObjects\CreateStudentData;
use Modules\People\Domain\DataObjects\DeactivateStudentGuardianData;
use Modules\People\Domain\DataObjects\LiabilityLineInput;
use Modules\People\Domain\DataObjects\LinkGuardianToStudentData;
use Modules\People\Domain\Exceptions\LastFeeResponsibleGuardianException;
use Modules\People\Domain\Exceptions\NoFeeResponsibleGuardianException;
use Modules\People\Domain\Support\LiabilityResolver;
use Modules\People\Models\Guardian;

/**
 * @return array<string, mixed>
 */
function liabilityFixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create();
    $section = SchoolSection::factory()->for($school)->create();
    $gradeLevel = GradeLevel::factory()->for($school)->for($section, 'section')->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'admission', pattern: '{SCHOOL}/{YEAR}/{SEQ:4}', academicYearId: $year->id,
    ));

    $student = app(CreateStudentAction::class)->execute(new CreateStudentData(
        schoolId: $school->id, academicYearId: $year->id, termId: $term->id,
        firstName: 'Tinashe', lastName: 'Moyo', dateOfBirth: now()->subYears(17), gender: 'male',
        enrolmentType: 'FULL_TIME', residency: 'BOARDER', sectionId: $section->id, gradeLevelId: $gradeLevel->id,
        entryCohortYear: (int) now()->year, createdByUserId: $user->id, skipDuplicateCheck: true,
    ));

    $tuition = FeeComponent::factory()->for($school)->create(['code' => 'TUITION']);
    $boarding = FeeComponent::factory()->for($school)->create(['code' => 'BOARDING']);
    $levy = FeeComponent::factory()->for($school)->create(['code' => 'LEVY']);
    $sports = FeeComponent::factory()->for($school)->create(['code' => 'SPORTS']);

    return [
        'school' => $school, 'year' => $year, 'term' => $term, 'user' => $user, 'student' => $student,
        'tuition' => $tuition, 'boarding' => $boarding, 'levy' => $levy, 'sports' => $sports,
    ];
}

/**
 * @param  array<string, mixed>  $f
 */
function makeGuardian(array $f, string $lastName): Guardian
{
    return app(CreateGuardianAction::class)->execute(new CreateGuardianData(
        schoolId: $f['school']->id, guardianType: 'individual', createdByUserId: $f['user']->id,
        firstName: 'Test', lastName: $lastName,
    ));
}

it('reproduces the worked example exactly: two full_component claims and a 60/40 split (Book C PPL-03 §5)', function (): void {
    $f = liabilityFixture();
    $father = makeGuardian($f, 'Father');
    $mother = makeGuardian($f, 'Mother');
    $employer = app(CreateGuardianAction::class)->execute(new CreateGuardianData(
        schoolId: $f['school']->id, guardianType: 'organisation', createdByUserId: $f['user']->id, organisationName: 'Delta Ltd',
    ));

    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $father->id, 'father', $f['user']->id, isFeeResponsible: true));
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $mother->id, 'mother', $f['user']->id));
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $employer->id, 'employer', $f['user']->id));

    app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData($f['school']->id, $f['student']->id, $employer->id, 'full_component', $f['user']->id, componentId: $f['boarding']->id, priority: 10));
    app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData($f['school']->id, $f['student']->id, $father->id, 'full_component', $f['user']->id, componentId: $f['tuition']->id, priority: 20));
    app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData($f['school']->id, $f['student']->id, $father->id, 'percentage', $f['user']->id, sharePercent: '60.00', priority: 30));
    app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData($f['school']->id, $f['student']->id, $mother->id, 'percentage', $f['user']->id, sharePercent: '40.00', priority: 40));

    $lines = collect([
        new LiabilityLineInput(1, $f['tuition']->id, 45000, 'USD'),
        new LiabilityLineInput(2, $f['boarding']->id, 60000, 'USD'),
        new LiabilityLineInput(3, $f['levy']->id, 120000, 'ZWG'),
        new LiabilityLineInput(4, $f['sports']->id, 2500, 'USD'),
    ]);

    $shares = app(LiabilityResolver::class)->resolve($f['student'], $lines, now());

    $byGuardian = $shares->groupBy('guardianId')->map(fn ($group) => $group->groupBy('currency')->map(fn ($g) => $g->sum('shareMinor')));

    expect($byGuardian[$father->id]['USD'])->toBe(45000 + 1500)
        ->and($byGuardian[$father->id]['ZWG'])->toBe(72000)
        ->and($byGuardian[$employer->id]['USD'])->toBe(60000)
        ->and($byGuardian[$mother->id]['USD'])->toBe(1000)
        ->and($byGuardian[$mother->id]['ZWG'])->toBe(48000);
});

it('splits a percentage share deterministically with no cent lost or duplicated (AC-PPL-03-005/BR-PPL-03-008)', function (): void {
    $f = liabilityFixture();
    $father = makeGuardian($f, 'Father');
    $mother = makeGuardian($f, 'Mother');
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $father->id, 'father', $f['user']->id, isFeeResponsible: true));

    app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData($f['school']->id, $f['student']->id, $father->id, 'percentage', $f['user']->id, sharePercent: '60.00', priority: 10));
    app(CreateFeeLiabilityAction::class)->execute(new CreateFeeLiabilityData($f['school']->id, $f['student']->id, $mother->id, 'percentage', $f['user']->id, sharePercent: '40.00', priority: 20));

    $lines = collect([new LiabilityLineInput(1, $f['tuition']->id, 10001, 'USD')]);
    $shares = app(LiabilityResolver::class)->resolve($f['student'], $lines, now());

    $fatherShare = $shares->firstWhere('guardianId', $father->id)->shareMinor;
    $motherShare = $shares->firstWhere('guardianId', $mother->id)->shareMinor;

    expect($fatherShare)->toBe(6001)->and($motherShare)->toBe(4000)->and($fatherShare + $motherShare)->toBe(10001);
});

it('falls the residue through to the default fee-responsible guardian when no rule covers it (BR-FIN-03-007)', function (): void {
    $f = liabilityFixture();
    $father = makeGuardian($f, 'Father');
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $father->id, 'father', $f['user']->id, isFeeResponsible: true));

    $lines = collect([new LiabilityLineInput(1, $f['tuition']->id, 45000, 'USD')]);
    $shares = app(LiabilityResolver::class)->resolve($f['student'], $lines, now());

    expect($shares)->toHaveCount(1)
        ->and($shares->first()->guardianId)->toBe($father->id)
        ->and($shares->first()->liabilityId)->toBeNull();
});

it('never bills a guardian merely for being linked — only is_fee_responsible or an explicit rule does (AC-PPL-03-003/BR-PPL-03-005)', function (): void {
    $f = liabilityFixture();
    $father = makeGuardian($f, 'Father');
    $mother = makeGuardian($f, 'Mother');
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $father->id, 'father', $f['user']->id, isFeeResponsible: false));
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $mother->id, 'mother', $f['user']->id, isFeeResponsible: true));

    $lines = collect([new LiabilityLineInput(1, $f['tuition']->id, 45000, 'USD')]);
    $shares = app(LiabilityResolver::class)->resolve($f['student'], $lines, now());

    expect($shares)->toHaveCount(1)->and($shares->first()->guardianId)->toBe($mother->id);
});

it('throws when a learner has no fee-responsible guardian to absorb the residue (BR-PPL-03-004)', function (): void {
    $f = liabilityFixture();
    $lines = collect([new LiabilityLineInput(1, $f['tuition']->id, 45000, 'USD')]);

    expect(fn () => app(LiabilityResolver::class)->resolve($f['student'], $lines, now()))
        ->toThrow(NoFeeResponsibleGuardianException::class);
});

it('refuses to deactivate the last fee-responsible guardian without a replacement (AC-PPL-03-009)', function (): void {
    $f = liabilityFixture();
    $father = makeGuardian($f, 'Father');
    $link = app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $father->id, 'father', $f['user']->id, isFeeResponsible: true));

    expect(fn () => app(DeactivateStudentGuardianAction::class)->execute(new DeactivateStudentGuardianData($link->id, $f['user']->id)))
        ->toThrow(LastFeeResponsibleGuardianException::class);

    $mother = makeGuardian($f, 'Mother');
    app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData($f['student']->id, $mother->id, 'mother', $f['user']->id, isFeeResponsible: true));

    $deactivated = app(DeactivateStudentGuardianAction::class)->execute(new DeactivateStudentGuardianData($link->id, $f['user']->id));
    expect($deactivated->status)->toBe('inactive');
});

it('lets a court-restricted guardian never collect regardless of the flag (BR-PPL-03-011/AC-PPL-03-004)', function (): void {
    $f = liabilityFixture();
    $guardian = makeGuardian($f, 'Uncle');
    $link = app(LinkGuardianToStudentAction::class)->execute(new LinkGuardianToStudentData(
        $f['student']->id, $guardian->id, 'uncle', $f['user']->id, mayCollectLearner: true, hasCourtRestriction: true,
    ));

    expect($link->canCollectLearner())->toBeFalse();
});

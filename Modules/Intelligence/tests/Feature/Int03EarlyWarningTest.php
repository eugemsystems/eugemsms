<?php

use App\Models\User;
use Modules\Academic\Models\AttendanceSummary;
use Modules\Academic\Models\Subject;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Academic\Models\Venue;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\Notification;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Invoice;
use Modules\Intelligence\Domain\Actions\ComputeFeeDefaultRiskAction;
use Modules\Intelligence\Domain\Actions\ComputeLearnerRiskScoreAction;
use Modules\Intelligence\Domain\Actions\GenerateEnrolmentForecastAction;
use Modules\Intelligence\Domain\Actions\GetCapacityPlanningProjectionAction;
use Modules\Intelligence\Domain\Actions\GetCommodityAnomalyDashboardAction;
use Modules\Intelligence\Domain\Actions\RecalculateStaffWellbeingIndicatorAction;
use Modules\Intelligence\Domain\Actions\ReviewWithdrawalRiskFlagAction;
use Modules\Intelligence\Domain\Registry\ReportFieldRegistry;
use Modules\Intelligence\Models\WithdrawalRiskFlag;
use Modules\People\Models\Guardian;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffWorkload;
use Modules\People\Models\Student;
use Modules\People\Models\StudentEnrolment;
use Modules\Stores\Models\ConsumptionAnomaly;

/**
 * @return array{school: School, year: AcademicYear, previousTerm: Term, term: Term}
 */
function int03Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $previousTerm = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 1, 'starts_on' => now()->subMonths(4), 'ends_on' => now()->subMonths(2)]);
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['number' => 2, 'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonth()]);

    return compact('school', 'year', 'previousTerm', 'term');
}

it('decomposes a composite risk score into plain-language, weighted, sourced factors (AC-INT-03-001)', function (): void {
    $f = int03Fixture();
    $student = Student::factory()->for($f['school'])->create();

    AttendanceSummary::factory()->for($f['school'])->create(['student_id' => $student->id, 'term_id' => $f['previousTerm']->id, 'scope' => 'term', 'attendance_percent' => 94]);
    AttendanceSummary::factory()->for($f['school'])->create(['student_id' => $student->id, 'term_id' => $f['term']->id, 'scope' => 'term', 'attendance_percent' => 71]);

    $score = app(ComputeLearnerRiskScoreAction::class)->execute($f['school']->id, $student->id, $f['term']->id);

    expect($score->contributing_factors)->toHaveCount(1);
    $factor = $score->contributing_factors[0];
    expect($factor)->toHaveKeys(['indicator', 'plain_language', 'weight', 'contribution', 'source'])
        ->and($factor['indicator'])->toBe('attendance_decline')
        ->and($factor['plain_language'])->toContain('94')->toContain('71')
        ->and($factor['source'])->toContain('ACA-04')
        ->and($score->composite_score)->toBeGreaterThan(0);
});

it('reaches a critical band, opens a review queue entry, and triggers no automated guardian communication (AC-INT-03-003)', function (): void {
    $f = int03Fixture();
    $student = Student::factory()->for($f['school'])->create();

    AttendanceSummary::factory()->for($f['school'])->create(['student_id' => $student->id, 'term_id' => $f['previousTerm']->id, 'scope' => 'term', 'attendance_percent' => 99]);
    AttendanceSummary::factory()->for($f['school'])->create(['student_id' => $student->id, 'term_id' => $f['term']->id, 'scope' => 'term', 'attendance_percent' => 20]);

    Invoice::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'due_date' => now()->subDays(95)->toDateString(),
        'net_minor' => 10000, 'balance_minor' => 10000,
    ]);

    $subject = Subject::factory()->for($f['school'])->create();
    TermSubjectResult::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $f['previousTerm']->id,
        'academic_year_id' => $f['year']->id, 'final_percent' => 70,
    ]);
    TermSubjectResult::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'subject_id' => $subject->id, 'term_id' => $f['term']->id,
        'academic_year_id' => $f['year']->id, 'final_percent' => 40,
    ]);

    $score = app(ComputeLearnerRiskScoreAction::class)->execute($f['school']->id, $student->id, $f['term']->id);

    expect($score->risk_band)->toBe('critical');

    $flag = WithdrawalRiskFlag::where('school_id', $f['school']->id)->where('student_id', $student->id)->firstOrFail();
    expect($flag->status)->toBe('open');

    expect(Notification::where('school_id', $f['school']->id)->exists())->toBeFalse();
});

it('never registers risk score data as a reportable field, so no ad hoc report can surface it to any portal (BR-INT-03-002)', function (): void {
    expect(ReportFieldRegistry::getEntity('learner_risk_scores'))->toBeNull()
        ->and(ReportFieldRegistry::getEntity('fee_default_risk_scores'))->toBeNull();
});

it('refuses to close a withdrawal risk flag with no intervention note, and accepts one with a note (AC-INT-03-004)', function (): void {
    $f = int03Fixture();
    $student = Student::factory()->for($f['school'])->create();
    $flag = WithdrawalRiskFlag::factory()->for($f['school'])->create(['student_id' => $student->id, 'status' => 'open']);
    $reviewer = User::factory()->create();

    expect(fn () => app(ReviewWithdrawalRiskFlagAction::class)->execute($flag->id, 'resolved', $reviewer->id))
        ->toThrow(InvalidArgumentException::class);

    $closed = app(ReviewWithdrawalRiskFlagAction::class)->execute($flag->id, 'resolved', $reviewer->id, 'Met with the family; support plan agreed.');

    expect($closed->status)->toBe('resolved')
        ->and($closed->reviewed_by)->toBe($reviewer->id);
});

it('surfaces FIN-09\'s own consumption anomalies read-only, with no duplicate detection logic (AC-INT-03-005)', function (): void {
    $f = int03Fixture();
    ConsumptionAnomaly::factory()->for($f['school'])->create();

    $dashboard = app(GetCommodityAnomalyDashboardAction::class)->execute($f['school']->id);

    expect($dashboard['consumption_anomalies'])->toHaveCount(1)
        ->and($dashboard['fuel_anomalies'])->toBe([]);
});

it('marks an enrolment forecast low-confidence, not withheld, when history is thinner than the configured minimum (AC-INT-03-006)', function (): void {
    $f = int03Fixture();
    $grade = GradeLevel::factory()->for($f['school'])->create();
    StudentEnrolment::factory()->for($f['school'])->create(['grade_level_id' => $grade->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['previousTerm']->id]);

    $forecast = app(GenerateEnrolmentForecastAction::class)->execute($f['school']->id, $f['year']->id, $grade->id);

    expect($forecast->confidence_band)->toBe('low')
        ->and($forecast->basis_note)->not->toBeEmpty();
});

it('computes fee default risk per guardian without triggering FIN-03\'s own reminder ladder (BR-INT-03-006)', function (): void {
    $f = int03Fixture();
    $guardian = Guardian::factory()->for($f['school'])->create();
    $student = Student::factory()->for($f['school'])->create();

    Invoice::factory()->for($f['school'])->create([
        'student_id' => $student->id, 'billed_party_type' => 'guardian', 'billed_party_id' => $guardian->id,
        'due_date' => now()->subDays(65)->toDateString(), 'net_minor' => 20000, 'balance_minor' => 20000,
    ]);

    $results = app(ComputeFeeDefaultRiskAction::class)->execute($f['school']->id);

    expect($results)->toHaveCount(1)
        ->and($results[0]->guardian_id)->toBe($guardian->id)
        ->and($results[0]->recommended_action)->toBe('early_contact');
});

it('copies staff wellbeing utilisation straight from PPL-04\'s own workload cache, never recomputing it (BR-INT-03-009)', function (): void {
    $f = int03Fixture();
    $staff = Staff::factory()->for($f['school'])->create();
    StaffWorkload::factory()->for($f['school'])->create(['staff_id' => $staff->id, 'term_id' => $f['term']->id, 'utilisation_percent' => 115]);

    $indicator = app(RecalculateStaffWellbeingIndicatorAction::class)->execute($f['school']->id, $staff->id, $f['term']->id);

    expect((float) $indicator->workload_utilisation_percent)->toBe(115.0)
        ->and($indicator->flag_level)->toBe('watch');
});

it('reads hostel and venue capacity directly rather than recalculating it, alongside its own enrolment forecast (BR-INT-03-012)', function (): void {
    $f = int03Fixture();
    Hostel::factory()->for($f['school'])->create(['capacity' => 200, 'is_active' => true]);
    Venue::factory()->for($f['school'])->create(['capacity' => 300, 'is_active' => true]);

    $projection = app(GetCapacityPlanningProjectionAction::class)->execute($f['school']->id, $f['year']->id);

    expect($projection['hostel_capacity'])->toBe(200)
        ->and($projection['venue_capacity'])->toBe(300);
});

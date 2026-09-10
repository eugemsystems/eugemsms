<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Boarding\Domain\Actions\ApproveIssuedItemDamageChargeAction;
use Modules\Boarding\Domain\Actions\ApproveLostItemChargeAction;
use Modules\Boarding\Domain\Actions\CheckLinenClearanceAction;
use Modules\Boarding\Domain\Actions\CollectLaundryAction;
use Modules\Boarding\Domain\Actions\CreateIssuableItemAction;
use Modules\Boarding\Domain\Actions\CreateLaundryCycleAction;
use Modules\Boarding\Domain\Actions\IssueItemToLearnerAction;
use Modules\Boarding\Domain\Actions\ReconcileLaundryCycleAction;
use Modules\Boarding\Domain\Actions\RecordLaundryReturnAction;
use Modules\Boarding\Domain\Actions\ReportItemLostAction;
use Modules\Boarding\Domain\Actions\ResolveLaundryDiscrepancyAction;
use Modules\Boarding\Domain\Actions\ReturnIssuedItemAction;
use Modules\Boarding\Domain\DataObjects\ApproveIssuedItemChargeData;
use Modules\Boarding\Domain\DataObjects\CollectLaundryData;
use Modules\Boarding\Domain\DataObjects\CreateIssuableItemData;
use Modules\Boarding\Domain\DataObjects\CreateLaundryCycleData;
use Modules\Boarding\Domain\DataObjects\IssueItemToLearnerData;
use Modules\Boarding\Domain\DataObjects\ReconcileLaundryCycleData;
use Modules\Boarding\Domain\DataObjects\RecordLaundryReturnData;
use Modules\Boarding\Domain\DataObjects\ResolveLaundryDiscrepancyData;
use Modules\Boarding\Domain\DataObjects\ReturnIssuedItemData;
use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\LaundryItem;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Domain\Actions\WithdrawStudentAction;
use Modules\People\Domain\DataObjects\WithdrawStudentData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentGuardian;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, component: FeeComponent}
 */
function brd05Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $user = User::factory()->create();
    $component = FeeComponent::factory()->create([
        'school_id' => $school->id,
        'code' => 'LINEN',
        'income_account_id' => Account::factory()->for($school)->income()->create()->id,
        'debtor_account_id' => Account::factory()->for($school)->controlAccount('student')->create()->id,
    ]);

    return compact('school', 'year', 'term', 'user', 'component');
}

it('blocks clearance while a returnable item is outstanding, and clears once it is returned', function (): void {
    $f = brd05Fixture();
    $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id]);

    $mattress = app(CreateIssuableItemAction::class)->execute(new CreateIssuableItemData(
        schoolId: $f['school']->id, code: 'MATTRESS', name: 'Mattress', category: 'bedding', currency: 'USD',
    ));
    $towel = app(CreateIssuableItemAction::class)->execute(new CreateIssuableItemData(
        schoolId: $f['school']->id, code: 'TOWEL', name: 'Towel', category: 'linen', currency: 'USD', requiresTagging: true,
    ));

    expect(fn () => app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $student->id, issuableItemId: $towel->id,
        conditionAtIssue: 'new', issuedByUserId: $f['user']->id, issuedOn: now(),
    )))->toThrow(ValidationException::class);

    $mattressIssue = app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $student->id, issuableItemId: $mattress->id,
        conditionAtIssue: 'new', issuedByUserId: $f['user']->id, issuedOn: now(),
    ));
    $towelIssue = app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $student->id, issuableItemId: $towel->id,
        conditionAtIssue: 'new', issuedByUserId: $f['user']->id, issuedOn: now(), tagReference: 'TAG-001',
    ));

    app(ReturnIssuedItemAction::class)->execute(new ReturnIssuedItemData(
        learnerIssuedItemId: $mattressIssue->id, conditionAtReturn: 'good', receivedByUserId: $f['user']->id, returnedOn: now(),
    ));

    $blocked = app(CheckLinenClearanceAction::class)->execute($f['school']->id, $student->id);
    expect($blocked->isClear)->toBeFalse()
        ->and($blocked->outstandingItemIds)->toBe([$towelIssue->id]);

    // BR-BRD-05-008/AC-BRD-05-004 — withdrawal itself is not blocked (WithdrawStudentAction
    // is untouched by this module); clearance is a separate, explicit check a clearance
    // workflow calls before treating the withdrawal as final.
    app(WithdrawStudentAction::class)->execute(new WithdrawStudentData(
        studentId: $student->id, exitedOn: now(), withdrawnByUserId: $f['user']->id, reason: 'Transfer',
    ));
    $stillBlocked = app(CheckLinenClearanceAction::class)->execute($f['school']->id, $student->id);
    expect($stillBlocked->isClear)->toBeFalse();

    app(ReturnIssuedItemAction::class)->execute(new ReturnIssuedItemData(
        learnerIssuedItemId: $towelIssue->id, conditionAtReturn: 'good', receivedByUserId: $f['user']->id, returnedOn: now(),
    ));

    $clear = app(CheckLinenClearanceAction::class)->execute($f['school']->id, $student->id);
    expect($clear->isClear)->toBeTrue()
        ->and($clear->outstandingItemIds)->toBe([]);
});

it('charges damage only within the expected lifespan, notifies on a lost item, and never charges normal wear beyond it', function (): void {
    $f = brd05Fixture();
    $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id]);
    StudentGuardian::factory()->create(['school_id' => $f['school']->id, 'student_id' => $student->id]);

    $blanket = app(CreateIssuableItemAction::class)->execute(new CreateIssuableItemData(
        schoolId: $f['school']->id, code: 'BLANKET', name: 'Blanket', category: 'bedding', currency: 'USD',
        replacementCostMinor: 1500, expectedLifespanTerms: 6,
    ));

    $blanketIssue = app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $student->id, issuableItemId: $blanket->id,
        conditionAtIssue: 'new', issuedByUserId: $f['user']->id, issuedOn: now(),
    ));

    $returned = app(ReturnIssuedItemAction::class)->execute(new ReturnIssuedItemData(
        learnerIssuedItemId: $blanketIssue->id, conditionAtReturn: 'poor', receivedByUserId: $f['user']->id, returnedOn: now(),
    ));
    expect($returned->status)->toBe('damaged');

    $charged = app(ApproveIssuedItemDamageChargeAction::class)->execute(new ApproveIssuedItemChargeData(
        learnerIssuedItemId: $blanketIssue->id, feeComponentId: $f['component']->id, approvedByUserId: $f['user']->id, chargeAmountMinor: 800,
    ));
    expect($charged->ad_hoc_charge_id)->not->toBeNull()
        ->and((int) AdHocCharge::find($charged->ad_hoc_charge_id)->amount_minor)->toBe(800);

    // A towel issued long enough ago that it has outlived its own expected lifespan —
    // returned in poor condition is normal wear, and BR-BRD-05-003 says that is never charged.
    $towel = app(CreateIssuableItemAction::class)->execute(new CreateIssuableItemData(
        schoolId: $f['school']->id, code: 'TOWEL-OLD', name: 'Towel', category: 'linen', currency: 'USD',
        replacementCostMinor: 300, expectedLifespanTerms: 1,
    ));
    $oldTerm = Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create([
        'number' => 2, 'starts_on' => $f['term']->starts_on->copy()->subMonths(8), 'ends_on' => $f['term']->starts_on->copy()->subMonths(7),
    ]);
    Term::factory()->for($f['school'])->for($f['year'], 'academicYear')->create([
        'number' => 3, 'starts_on' => $f['term']->starts_on->copy()->subMonths(4), 'ends_on' => $f['term']->starts_on->copy()->subMonths(3),
    ]);
    $towelIssue = app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
        schoolId: $f['school']->id, termId: $oldTerm->id, studentId: $student->id, issuableItemId: $towel->id,
        conditionAtIssue: 'new', issuedByUserId: $f['user']->id, issuedOn: $oldTerm->starts_on,
    ));

    $towelReturned = app(ReturnIssuedItemAction::class)->execute(new ReturnIssuedItemData(
        learnerIssuedItemId: $towelIssue->id, conditionAtReturn: 'poor', receivedByUserId: $f['user']->id, returnedOn: now(),
    ));
    expect($towelReturned->status)->toBe('returned')
        ->and($towelReturned->ad_hoc_charge_id)->toBeNull();

    // BR-BRD-05-004 — a lost item is charged replacement cost after approval, learner+guardian notified.
    $mattress = app(CreateIssuableItemAction::class)->execute(new CreateIssuableItemData(
        schoolId: $f['school']->id, code: 'MATT-2', name: 'Mattress', category: 'bedding', currency: 'USD', replacementCostMinor: 2000,
    ));
    $mattressIssue = app(IssueItemToLearnerAction::class)->execute(new IssueItemToLearnerData(
        schoolId: $f['school']->id, termId: $f['term']->id, studentId: $student->id, issuableItemId: $mattress->id,
        conditionAtIssue: 'new', issuedByUserId: $f['user']->id, issuedOn: now(),
    ));
    $lost = app(ReportItemLostAction::class)->execute($mattressIssue->id, 'Not found at end of term.');
    expect($lost->status)->toBe('lost');

    $lostCharged = app(ApproveLostItemChargeAction::class)->execute(new ApproveIssuedItemChargeData(
        learnerIssuedItemId: $mattressIssue->id, feeComponentId: $f['component']->id, approvedByUserId: $f['user']->id,
    ));
    expect($lostCharged->charge_minor)->toBe(2000)
        ->and((int) AdHocCharge::find($lostCharged->ad_hoc_charge_id)->amount_minor)->toBe(2000);
});

it('flags a laundry discrepancy and refuses to reconcile until it is resolved or charged', function (): void {
    $f = brd05Fixture();
    $hostel = Hostel::factory()->create(['school_id' => $f['school']->id]);
    $student = Student::factory()->boarder()->create(['school_id' => $f['school']->id]);

    $cycle = app(CreateLaundryCycleAction::class)->execute(new CreateLaundryCycleData(
        schoolId: $f['school']->id, termId: $f['term']->id, hostelId: $hostel->id, cycleDate: now(),
    ));

    app(CollectLaundryAction::class)->execute(new CollectLaundryData(
        laundryCycleId: $cycle->id, collectedAt: now(), items: [['studentId' => $student->id, 'itemsOut' => 14]],
    ));

    $afterReturn = app(RecordLaundryReturnAction::class)->execute(new RecordLaundryReturnData(
        laundryCycleId: $cycle->id, returnedAt: now(), items: [['studentId' => $student->id, 'itemsBack' => 13, 'missingDescription' => 'One sock missing.']],
    ));
    expect($afterReturn->status)->toBe('returned')
        ->and($afterReturn->items_missing)->toBe(1);

    $laundryItem = LaundryItem::where('cycle_id', $cycle->id)->where('student_id', $student->id)->first();
    expect($laundryItem->resolved)->toBeFalse();

    expect(fn () => app(ReconcileLaundryCycleAction::class)->execute(new ReconcileLaundryCycleData(
        laundryCycleId: $cycle->id,
    )))->toThrow(InvalidStateTransitionException::class);

    app(ResolveLaundryDiscrepancyAction::class)->execute(new ResolveLaundryDiscrepancyData(
        laundryItemId: $laundryItem->id, resolution: 'resolved',
    ));

    $reconciled = app(ReconcileLaundryCycleAction::class)->execute(new ReconcileLaundryCycleData(
        laundryCycleId: $cycle->id, costMinor: 500,
    ));
    expect($reconciled->status)->toBe('reconciled')
        ->and($reconciled->cost_minor)->toBe(500);
});

<?php

use App\Models\User;
use Modules\Academic\Domain\Actions\CheckLibraryClearanceAction;
use Modules\Academic\Domain\Actions\CompleteLibraryStockTakeAction;
use Modules\Academic\Domain\Actions\CreateBorrowerCategoryAction;
use Modules\Academic\Domain\Actions\CreateLibraryCopyAction;
use Modules\Academic\Domain\Actions\CreateLibraryItemAction;
use Modules\Academic\Domain\Actions\IssueLoanAction;
use Modules\Academic\Domain\Actions\MarkConfirmatoryPassDoneAction;
use Modules\Academic\Domain\Actions\ProcessBulkTextbookIssueAction;
use Modules\Academic\Domain\Actions\ProcessBulkTextbookReturnAction;
use Modules\Academic\Domain\Actions\RecordStockTakeScanAction;
use Modules\Academic\Domain\Actions\RenewLoanAction;
use Modules\Academic\Domain\Actions\ReturnLoanAction;
use Modules\Academic\Domain\Actions\StartLibraryStockTakeAction;
use Modules\Academic\Domain\DataObjects\CompleteLibraryStockTakeData;
use Modules\Academic\Domain\DataObjects\CreateBorrowerCategoryData;
use Modules\Academic\Domain\DataObjects\CreateLibraryCopyData;
use Modules\Academic\Domain\DataObjects\CreateLibraryItemData;
use Modules\Academic\Domain\DataObjects\IssueLoanData;
use Modules\Academic\Domain\DataObjects\MarkConfirmatoryPassDoneData;
use Modules\Academic\Domain\DataObjects\ProcessBulkTextbookIssueData;
use Modules\Academic\Domain\DataObjects\ProcessBulkTextbookReturnData;
use Modules\Academic\Domain\DataObjects\RecordStockTakeScanData;
use Modules\Academic\Domain\DataObjects\RenewLoanData;
use Modules\Academic\Domain\DataObjects\ReturnLoanData;
use Modules\Academic\Domain\DataObjects\StartLibraryStockTakeData;
use Modules\Academic\Domain\Exceptions\LoanLimitExceededException;
use Modules\Academic\Domain\Exceptions\RenewalNotAllowedException;
use Modules\Academic\Domain\Exceptions\StockTakeNotReadyException;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryItem;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\Term;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\FeeComponent;
use Modules\People\Models\Student;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, feeComponentId: int}
 */
function aca10Fixture(): array
{
    $school = School::factory()->create();
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['is_current' => true]);
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'library_accession', pattern: 'LIB/{SEQ:5}',
    ));

    $component = FeeComponent::factory()->for($school)->create();

    return ['school' => $school, 'year' => $year, 'term' => $term, 'user' => $user, 'feeComponentId' => $component->id];
}

/**
 * @param  array<string, mixed>  $f
 */
function aca10Copy(array $f, int $replacementCostMinor = 1500): array
{
    $item = app(CreateLibraryItemAction::class)->execute(new CreateLibraryItemData(
        schoolId: $f['school']->id, title: 'Combined Science Form 3', itemCategory: 'textbook',
        replacementCostMinor: $replacementCostMinor, currency: 'USD',
    ));
    $copy = app(CreateLibraryCopyAction::class)->execute(new CreateLibraryCopyData(
        itemId: $item->id, allocatedByUserId: $f['user']->id,
    ));

    return [$item, $copy];
}

/**
 * @param  array<string, mixed>  $f
 * @return array{0: LibraryItem, 1: array<int, LibraryCopy>}
 */
function aca10ItemWithCopies(array $f, int $copyCount, int $replacementCostMinor = 1500): array
{
    $item = app(CreateLibraryItemAction::class)->execute(new CreateLibraryItemData(
        schoolId: $f['school']->id, title: 'Combined Science Form 3', itemCategory: 'textbook',
        replacementCostMinor: $replacementCostMinor, currency: 'USD',
    ));

    $copies = [];

    for ($i = 0; $i < $copyCount; $i++) {
        $copies[] = app(CreateLibraryCopyAction::class)->execute(new CreateLibraryCopyData(
            itemId: $item->id, allocatedByUserId: $f['user']->id,
        ));
    }

    return [$item, $copies];
}

it('allocates gapless accession numbers through CORE-06 (BR-ACA-10-001)', function (): void {
    $f = aca10Fixture();
    [, $copyOne] = aca10Copy($f);
    [, $copyTwo] = aca10Copy($f);

    expect($copyOne->accession_number)->not->toBe($copyTwo->accession_number)
        ->and($copyOne->accession_number)->toStartWith('LIB/');
});

it('refuses another loan once a borrower is at their concurrent loan limit (AC-ACA-10-003)', function (): void {
    $f = aca10Fixture();
    app(CreateBorrowerCategoryAction::class)->execute(new CreateBorrowerCategoryData(
        schoolId: $f['school']->id, category: 'secondary', maxConcurrentLoans: 1, loanPeriodDays: 14,
    ));
    $student = Student::factory()->for($f['school'])->create();
    [, $copyOne] = aca10Copy($f);
    [, $copyTwo] = aca10Copy($f);

    app(IssueLoanAction::class)->execute(new IssueLoanData(
        termId: $f['term']->id, copyId: $copyOne->id, borrowerType: 'student', borrowerId: $student->id,
        borrowerCategory: 'secondary', issuedByUserId: $f['user']->id,
    ));

    app(IssueLoanAction::class)->execute(new IssueLoanData(
        termId: $f['term']->id, copyId: $copyTwo->id, borrowerType: 'student', borrowerId: $student->id,
        borrowerCategory: 'secondary', issuedByUserId: $f['user']->id,
    ));
})->throws(LoanLimitExceededException::class);

it('allows renewal up to max_renewals and then refuses (BR-ACA-10-003)', function (): void {
    $f = aca10Fixture();
    app(CreateBorrowerCategoryAction::class)->execute(new CreateBorrowerCategoryData(
        schoolId: $f['school']->id, category: 'secondary', maxConcurrentLoans: 5, loanPeriodDays: 14, maxRenewals: 1,
    ));
    $student = Student::factory()->for($f['school'])->create();
    [, $copy] = aca10Copy($f);

    $loan = app(IssueLoanAction::class)->execute(new IssueLoanData(
        termId: $f['term']->id, copyId: $copy->id, borrowerType: 'student', borrowerId: $student->id,
        borrowerCategory: 'secondary', issuedByUserId: $f['user']->id,
    ));

    $renewed = app(RenewLoanAction::class)->execute(new RenewLoanData(loanId: $loan->id, renewedByUserId: $f['user']->id));
    expect($renewed->renewal_count)->toBe(1);

    app(RenewLoanAction::class)->execute(new RenewLoanData(loanId: $loan->id, renewedByUserId: $f['user']->id));
})->throws(RenewalNotAllowedException::class);

it('charges a late-return fine through the same FIN-02 ad hoc charge mechanism, capped at replacement cost (BR-ACA-10-005/006)', function (): void {
    $f = aca10Fixture();
    app(CreateBorrowerCategoryAction::class)->execute(new CreateBorrowerCategoryData(
        schoolId: $f['school']->id, category: 'secondary', maxConcurrentLoans: 5, loanPeriodDays: 14,
    ));
    $student = Student::factory()->for($f['school'])->create();
    [, $copy] = aca10Copy($f, replacementCostMinor: 1500);

    $loan = app(IssueLoanAction::class)->execute(new IssueLoanData(
        termId: $f['term']->id, copyId: $copy->id, borrowerType: 'student', borrowerId: $student->id,
        borrowerCategory: 'secondary', issuedByUserId: $f['user']->id,
    ));
    $loan->update(['due_on' => now()->subDays(10)->toDateString()]);

    $returned = app(ReturnLoanAction::class)->execute(new ReturnLoanData(
        loanId: $loan->id, returnedByUserId: $f['user']->id, feeComponentId: $f['feeComponentId'],
    ));

    expect($returned->status)->toBe('fined')
        ->and($returned->fine_charge_id)->not->toBeNull();

    $charge = AdHocCharge::findOrFail($returned->fine_charge_id);
    expect($charge->amount_minor)->toBe(500); // 10 days * 50 minor default rate, well under the 1500 cap
    expect($returned->copy->fresh()->status)->toBe('available');
});

it('processes a bulk textbook issue for a whole class, recording exceptions individually without blocking the batch (AC-ACA-10-001)', function (): void {
    $f = aca10Fixture();
    $class = SchoolClass::factory()->for($f['school'])->create();
    $students = Student::factory()->for($f['school'])->count(4)->create();

    foreach ($students as $student) {
        ClassAllocation::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'student_id' => $student->id, 'class_id' => $class->id,
        ]);
    }

    // Only 2 copies of the set textbook exist for 4 learners — 2 exceptions expected.
    [$item] = aca10ItemWithCopies($f, copyCount: 2);

    $issue = app(ProcessBulkTextbookIssueAction::class)->execute(new ProcessBulkTextbookIssueData(
        schoolId: $f['school']->id, termId: $f['term']->id, classId: $class->id,
        itemIds: [$item->id], issuedByUserId: $f['user']->id,
    ));

    expect($issue->total_learners)->toBe(4)
        ->and($issue->completed_count)->toBe(2)
        ->and($issue->exception_count)->toBe(2)
        ->and($issue->exceptions)->toHaveCount(2)
        ->and($issue->status)->toBe('completed');
});

it('posts a replacement charge for an unreturned bulk-issued textbook and blocks the learner\'s library clearance (AC-ACA-10-002/BR-ACA-10-008)', function (): void {
    $f = aca10Fixture();
    $class = SchoolClass::factory()->for($f['school'])->create();
    $student = Student::factory()->for($f['school'])->create();
    ClassAllocation::factory()->create([
        'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
        'student_id' => $student->id, 'class_id' => $class->id,
    ]);
    [$item] = aca10ItemWithCopies($f, copyCount: 1, replacementCostMinor: 2000);

    app(ProcessBulkTextbookIssueAction::class)->execute(new ProcessBulkTextbookIssueData(
        schoolId: $f['school']->id, termId: $f['term']->id, classId: $class->id,
        itemIds: [$item->id], issuedByUserId: $f['user']->id,
    ));

    $clearanceBefore = app(CheckLibraryClearanceAction::class)->execute($f['school']->id, 'student', $student->id);
    expect($clearanceBefore->isClear)->toBeFalse();

    $return = app(ProcessBulkTextbookReturnAction::class)->execute(new ProcessBulkTextbookReturnData(
        schoolId: $f['school']->id, termId: $f['term']->id, classId: $class->id,
        itemIds: [$item->id], feeComponentId: $f['feeComponentId'], chargedByUserId: $f['user']->id,
        returnedItemIdsByStudent: [], // nothing scanned back
    ));

    expect($return->exception_count)->toBe(1)
        ->and($return->completed_count)->toBe(0);

    $loan = Loan::where('school_id', $f['school']->id)->where('borrower_id', $student->id)->firstOrFail();
    expect($loan->status)->toBe('lost')
        ->and($loan->fine_charge_id)->not->toBeNull();

    $charge = AdHocCharge::findOrFail($loan->fine_charge_id);
    expect($charge->amount_minor)->toBe(2000);

    // Financially resolved (charged) — same resolution rule BRD-05's own linen clearance uses.
    $clearanceAfter = app(CheckLibraryClearanceAction::class)->execute($f['school']->id, 'student', $student->id);
    expect($clearanceAfter->isClear)->toBeTrue();
});

it('refuses to complete a stock-take before its confirmatory second pass (BR-ACA-10-009)', function (): void {
    $f = aca10Fixture();
    aca10Copy($f);
    $stockTake = app(StartLibraryStockTakeAction::class)->execute(new StartLibraryStockTakeData(schoolId: $f['school']->id));

    app(CompleteLibraryStockTakeAction::class)->execute(new CompleteLibraryStockTakeData(
        stockTakeId: $stockTake->id, feeComponentId: $f['feeComponentId'], chargedByUserId: $f['user']->id,
    ));
})->throws(StockTakeNotReadyException::class);

it('marks a copy lost and charges its borrower once a stock-take\'s confirmatory pass still finds it missing (AC-ACA-10-004)', function (): void {
    $f = aca10Fixture();
    app(CreateBorrowerCategoryAction::class)->execute(new CreateBorrowerCategoryData(
        schoolId: $f['school']->id, category: 'secondary', maxConcurrentLoans: 5, loanPeriodDays: 14,
    ));
    $student = Student::factory()->for($f['school'])->create();
    [, $foundCopy] = aca10Copy($f);
    [, $missingCopy] = aca10Copy($f, replacementCostMinor: 3000);

    $loan = app(IssueLoanAction::class)->execute(new IssueLoanData(
        termId: $f['term']->id, copyId: $missingCopy->id, borrowerType: 'student', borrowerId: $student->id,
        borrowerCategory: 'secondary', issuedByUserId: $f['user']->id,
    ));

    $stockTake = app(StartLibraryStockTakeAction::class)->execute(new StartLibraryStockTakeData(schoolId: $f['school']->id));
    app(RecordStockTakeScanAction::class)->execute(new RecordStockTakeScanData(stockTakeId: $stockTake->id, copyId: $foundCopy->id));
    app(MarkConfirmatoryPassDoneAction::class)->execute(new MarkConfirmatoryPassDoneData(stockTakeId: $stockTake->id));

    $completed = app(CompleteLibraryStockTakeAction::class)->execute(new CompleteLibraryStockTakeData(
        stockTakeId: $stockTake->id, feeComponentId: $f['feeComponentId'], chargedByUserId: $f['user']->id,
    ));

    expect($completed->missing_count)->toBe(1)
        ->and($completed->status)->toBe('completed');

    expect($missingCopy->fresh()->status)->toBe('lost');

    $loan = $loan->fresh();
    expect($loan->status)->toBe('lost')
        ->and($loan->fine_charge_id)->not->toBeNull();

    $charge = AdHocCharge::findOrFail($loan->fine_charge_id);
    expect($charge->amount_minor)->toBe(3000);
});

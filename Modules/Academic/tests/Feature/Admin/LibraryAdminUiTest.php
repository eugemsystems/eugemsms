<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Livewire;
use Modules\Academic\Domain\Actions\CheckLibraryClearanceAction;
use Modules\Academic\Domain\Actions\CreateBorrowerCategoryAction;
use Modules\Academic\Domain\Actions\IssueLoanAction;
use Modules\Academic\Domain\DataObjects\CreateBorrowerCategoryData;
use Modules\Academic\Domain\DataObjects\IssueLoanData;
use Modules\Academic\Livewire\Library\Acquisitions;
use Modules\Academic\Livewire\Library\BulkIssue;
use Modules\Academic\Livewire\Library\Catalogue;
use Modules\Academic\Livewire\Library\Circulation;
use Modules\Academic\Livewire\Library\Overdue;
use Modules\Academic\Livewire\Library\StockTake;
use Modules\Academic\Models\AcquisitionRequest;
use Modules\Academic\Models\BulkTextbookIssue;
use Modules\Academic\Models\ClassAllocation;
use Modules\Academic\Models\LibraryCopy;
use Modules\Academic\Models\LibraryItem;
use Modules\Academic\Models\LibraryStockTake;
use Modules\Academic\Models\Loan;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Finance\Models\AdHocCharge;
use Modules\Finance\Models\CostCentre;
use Modules\People\Models\Department;
use Modules\People\Models\Student;
use Modules\Stores\Models\PurchaseRequisition;

/**
 * Book K ACA-10 admin-UI pass. Reuses `aca10Fixture()`/`aca10Copy()`/
 * `aca10ItemWithCopies()` from `Aca10LibraryTest`, so run the module directory.
 *
 * @param  array<string, mixed>  $f
 */
function libAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $name): PermissionGrantData {
        $parts = explode('.', $name);
        $permission = Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module_code' => strtoupper($parts[0]), 'resource' => $parts[1] ?? $parts[0], 'action' => end($parts)]);

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(userId: $user->id, schoolId: $f['school']->id, grants: $grants));

    return $user;
}

/**
 * @param  array<string, mixed>  $f
 */
function libAdminCategory(array $f, int $maxLoans = 3): void
{
    app(CreateBorrowerCategoryAction::class)->execute(new CreateBorrowerCategoryData(schoolId: $f['school']->id, category: 'secondary', maxConcurrentLoans: $maxLoans, loanPeriodDays: 14));
}

/**
 * @param  array<string, mixed>  $f
 */
function libAdminLoan(array $f, Student $student, LibraryCopy $copy): Loan
{
    return app(IssueLoanAction::class)->execute(new IssueLoanData(
        termId: $f['term']->id, copyId: $copy->id, borrowerType: 'student', borrowerId: $student->id, borrowerCategory: 'secondary', issuedByUserId: $f['user']->id,
    ));
}

it('refuses every library screen to a user without its permission', function (string $component): void {
    $f = aca10Fixture();
    $user = libAdminUser($f, 'cbt.bank.manage');
    $this->actingAs($user);

    Livewire::test($component, ['school' => $f['school']])->assertForbidden();
})->with([Catalogue::class, Circulation::class, BulkIssue::class, Overdue::class, StockTake::class, Acquisitions::class]);

it('lets a manager catalogue titles, accession copies and set loan limits, and refuses a duplicate ISBN', function (): void {
    $f = aca10Fixture();
    $this->actingAs(libAdminUser($f, 'library.view', 'library.catalogue.manage'));

    $page = Livewire::test(Catalogue::class, ['school' => $f['school']])
        ->set('title', 'Physics Today')->set('isbn', '978-1')->set('itemCategory', 'textbook')->set('replacementCost', '12.50')
        ->call('addItem')->assertHasNoErrors();

    $item = LibraryItem::where('title', 'Physics Today')->firstOrFail();
    expect($item->replacement_cost_minor)->toBe(1250);

    $page->set('copyItemId', $item->id)->set('copyCount', 3)->call('addCopies')->assertHasNoErrors();
    expect(LibraryCopy::where('item_id', $item->id)->count())->toBe(3);

    $page->set('title', 'Physics Again')->set('isbn', '978-1')->call('addItem')->assertHasErrors('title');
    $page->set('borrowerCategoryName', 'staff')->set('maxLoans', 5)->call('saveBorrowerCategory')->assertHasNoErrors();
    $page->call('saveBorrowerCategory')->assertHasErrors('borrowerCategoryName');
});

it('lets a viewer search but not catalogue', function (): void {
    $f = aca10Fixture();
    aca10Copy($f);
    $this->actingAs(libAdminUser($f, 'library.view'));

    Livewire::test(Catalogue::class, ['school' => $f['school']])
        ->set('search', 'Combined')->assertSee('Combined Science Form 3')
        ->set('title', 'Sneaky')->call('addItem')->assertForbidden();
});

it('issues at the desk, refuses a learner at their limit, and will not accept a borrower from another school', function (): void {
    $f = aca10Fixture();
    libAdminCategory($f, maxLoans: 1);
    $student = Student::factory()->for($f['school'])->create();
    [, $copyOne] = aca10Copy($f);
    [, $copyTwo] = aca10Copy($f);
    $this->actingAs(libAdminUser($f, 'library.circulate'));

    $page = Livewire::test(Circulation::class, ['school' => $f['school']])
        ->set('borrowerType', 'student')->call('selectBorrower', $student->id)
        ->set('borrowerCategory', 'secondary')->set('copyCode', $copyOne->accession_number)->call('issue')->assertHasNoErrors();

    expect(Loan::where('borrower_id', $student->id)->where('status', 'active')->count())->toBe(1);

    $page->set('copyCode', $copyTwo->accession_number)->call('issue')->assertHasErrors('copyCode');
    expect($copyTwo->fresh()->status)->toBe('available');

    $stranger = Student::factory()->for(School::factory()->create())->create();
    Livewire::test(Circulation::class, ['school' => $f['school']])->set('borrowerType', 'student')->call('selectBorrower', $stranger->id)->assertSet('borrowerId', null);
});

it('requires a fee component for a late return, then charges the capped fine', function (): void {
    $f = aca10Fixture();
    libAdminCategory($f);
    $student = Student::factory()->for($f['school'])->create();
    [, $copy] = aca10Copy($f, replacementCostMinor: 120);
    $loan = libAdminLoan($f, $student, $copy);
    $loan->update(['issued_on' => now()->subDays(30), 'due_on' => now()->subDays(10)]);
    $this->actingAs(libAdminUser($f, 'library.circulate'));

    $page = Livewire::test(Circulation::class, ['school' => $f['school']])->set('returnCode', $copy->accession_number)->call('returnCopy')->assertHasErrors('returnCode');
    expect($loan->fresh()->status)->toBe('active');

    $page->set('feeComponentId', $f['feeComponentId'])->call('returnCopy')->assertHasNoErrors();
    $charge = AdHocCharge::where('source_type', 'loan')->where('source_id', $loan->id)->firstOrFail();
    expect($loan->fresh()->status)->toBe('fined')->and($charge->amount_minor)->toBe(120)->and($copy->fresh()->status)->toBe('available');
});

it('renews, and charges the replacement cost when a copy is marked lost', function (): void {
    $f = aca10Fixture();
    libAdminCategory($f);
    $student = Student::factory()->for($f['school'])->create();
    [, $copy] = aca10Copy($f, replacementCostMinor: 1500);
    $loan = libAdminLoan($f, $student, $copy);
    $this->actingAs(libAdminUser($f, 'library.circulate'));

    $page = Livewire::test(Circulation::class, ['school' => $f['school']])->set('borrowerType', 'student')->call('selectBorrower', $student->id);
    $page->call('renew', $loan->id);
    expect($loan->fresh()->renewal_count)->toBe(1);

    $page->call('markLost', $loan->id);
    expect($loan->fresh()->status)->toBe('active');

    $page->set('feeComponentId', $f['feeComponentId'])->call('markLost', $loan->id);
    expect($loan->fresh()->status)->toBe('lost')->and(AdHocCharge::where('source_id', $loan->id)->value('amount_minor'))->toBe(1500);
});

/**
 * @param  array<string, mixed>  $f
 * @return array{0: SchoolClass, 1: Collection<int, Student>}
 */
function libAdminClass(array $f, int $learners): array
{
    $f['term']->update(['starts_on' => now()->subMonth()]);
    $class = SchoolClass::factory()->for($f['school'])->create();
    $students = Student::factory()->for($f['school'])->count($learners)->create();

    foreach ($students as $student) {
        ClassAllocation::factory()->create([
            'school_id' => $f['school']->id, 'academic_year_id' => $f['year']->id, 'term_id' => $f['term']->id,
            'student_id' => $student->id, 'class_id' => $class->id,
        ]);
    }

    return [$class, $students];
}

it('bulk-issues to a class with exceptions listed, does not double-issue, and charges books flagged not returned', function (): void {
    $f = aca10Fixture();
    [$class, $students] = libAdminClass($f, 3);
    [$item] = aca10ItemWithCopies($f, copyCount: 2, replacementCostMinor: 900);
    $this->actingAs(libAdminUser($f, 'library.bulk_issue'));

    $page = Livewire::test(BulkIssue::class, ['school' => $f['school']])->set('classId', $class->id)->set('itemIds', [$item->id])->call('run')->assertHasNoErrors();

    $issue = BulkTextbookIssue::where('issue_type', 'term_start_issue')->firstOrFail();
    expect($issue->completed_count)->toBe(2)->and($issue->exception_count)->toBe(1);

    $page->call('run');
    expect(Loan::where('status', 'active')->count())->toBe(2);

    $holder = Loan::where('status', 'active')->firstOrFail();
    $page->set('mode', 'return')->set('notReturned', ["{$holder->borrower_id}:{$item->id}"]);
    $page->call('run')->assertHasErrors('feeComponentId');

    $page->set('feeComponentId', $f['feeComponentId'])->call('run')->assertHasNoErrors();
    expect($holder->fresh()->status)->toBe('lost')
        ->and(AdHocCharge::where('source_id', $holder->id)->value('amount_minor'))->toBe(900)
        ->and(app(CheckLibraryClearanceAction::class)->execute($f['school']->id, 'student', $holder->borrower_id)->isClear)->toBeTrue();
});

it('lists overdue loans and only a circulator can send a reminder', function (): void {
    $f = aca10Fixture();
    libAdminCategory($f);
    $student = Student::factory()->for($f['school'])->create(['first_name' => 'Tendai']);
    [, $copy] = aca10Copy($f);
    $loan = libAdminLoan($f, $student, $copy);
    $loan->update(['due_on' => now()->subDays(3)]);

    $this->actingAs(libAdminUser($f, 'library.view'));
    Livewire::test(Overdue::class, ['school' => $f['school']])->assertSee('Tendai')->assertSee('Combined Science Form 3')->call('remind', $loan->id)->assertForbidden();

    $this->actingAs(libAdminUser($f, 'library.view', 'library.circulate'));
    Livewire::test(Overdue::class, ['school' => $f['school']])->call('remind', $loan->id)->assertHasNoErrors();
});

it('runs a stock-take through two passes and charges the borrower of a copy that stays missing', function (): void {
    $f = aca10Fixture();
    libAdminCategory($f);
    $student = Student::factory()->for($f['school'])->create();
    [, $onLoan] = aca10Copy($f, replacementCostMinor: 700);
    [, $shelved] = aca10Copy($f);
    $loan = libAdminLoan($f, $student, $onLoan);
    $this->actingAs(libAdminUser($f, 'library.stocktake'));

    $page = Livewire::test(StockTake::class, ['school' => $f['school']])->call('start');
    $page->call('start');
    expect(LibraryStockTake::count())->toBe(1);

    $page->set('scanCode', $shelved->accession_number)->call('scan')->assertHasNoErrors();
    $page->set('scanCode', 'NOT-A-COPY')->call('scan')->assertHasErrors('scanCode');

    $page->set('feeComponentId', $f['feeComponentId'])->call('complete')->assertHasErrors('feeComponentId');
    expect(LibraryCopy::find($onLoan->id)->status)->toBe('on_loan');

    $page->call('confirmSecondPass')->set('feeComponentId', $f['feeComponentId'])->call('complete')->assertHasNoErrors();
    expect($onLoan->fresh()->status)->toBe('lost')->and($loan->fresh()->status)->toBe('lost')->and(AdHocCharge::where('source_id', $loan->id)->value('amount_minor'))->toBe(700);
});

it('routes an approved acquisition to a purchase requisition, and only an approver may decide', function (): void {
    $f = aca10Fixture();
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $f['school']->id, documentType: 'purchase_requisition', pattern: 'PR/{SEQ:5}'));
    $f['term']->update(['starts_on' => now()->subMonth()]);
    $department = Department::factory()->for($f['school'])->create();
    $costCentre = CostCentre::factory()->for($f['school'])->create();

    $this->actingAs(libAdminUser($f, 'library.acquisition.request'));
    $page = Livewire::test(Acquisitions::class, ['school' => $f['school']])->set('requestedTitle', 'Biology Atlas')->set('copies', 5)->set('estimatedCost', '100')->call('submit')->assertHasNoErrors();
    $request = AcquisitionRequest::where('requested_title', 'Biology Atlas')->firstOrFail();
    expect($request->estimated_cost_minor)->toBe(10000);

    $page->set('requestedTitle', '  ')->call('submit')->assertHasErrors('requestedTitle');
    $page->call('startApproval', $request->id)->assertForbidden();

    $this->actingAs(libAdminUser($f, 'library.acquisition.request', 'library.acquisition.approve'));
    Livewire::test(Acquisitions::class, ['school' => $f['school']])
        ->call('startApproval', $request->id)->set('departmentId', $department->id)->set('costCentreId', $costCentre->id)->call('approve')->assertHasNoErrors();

    expect($request->fresh()->status)->toBe('approved')
        ->and(PurchaseRequisition::where('source_type', 'acquisition_request')->where('source_id', $request->id)->exists())->toBeTrue();
});

it('rejects a pending acquisition request', function (): void {
    $f = aca10Fixture();
    $request = AcquisitionRequest::create(['school_id' => $f['school']->id, 'requested_title' => 'Atlas', 'requested_by' => $f['user']->id, 'copies_requested' => 1, 'status' => 'requested']);
    $this->actingAs(libAdminUser($f, 'library.acquisition.request', 'library.acquisition.approve'));

    Livewire::test(Acquisitions::class, ['school' => $f['school']])->call('reject', $request->id)->assertHasNoErrors();
    expect($request->fresh()->status)->toBe('rejected');
});

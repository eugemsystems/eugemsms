<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Boarding\Models\HostelDamage;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Operations\Domain\Actions\ApproveWorkOrderAction;
use Modules\Operations\Domain\Actions\CheckOverdueCriticalAssetAction;
use Modules\Operations\Domain\Actions\CheckUnverifiedWorkOrdersAction;
use Modules\Operations\Domain\Actions\CompleteCapitalProjectAction;
use Modules\Operations\Domain\Actions\CompleteWorkOrderAction;
use Modules\Operations\Domain\Actions\CreateCapitalProjectAction;
use Modules\Operations\Domain\Actions\CreateWorkOrderAction;
use Modules\Operations\Domain\Actions\GeneratePreventiveWorkOrdersAction;
use Modules\Operations\Domain\Actions\IssuePartsToWorkOrderAction;
use Modules\Operations\Domain\Actions\MarkFaultReportDuplicateAction;
use Modules\Operations\Domain\Actions\RaiseWorkOrderForHostelDamageAction;
use Modules\Operations\Domain\Actions\RecordContractorCostAction;
use Modules\Operations\Domain\Actions\RecordWorkOrderLabourAction;
use Modules\Operations\Domain\Actions\RejectFaultReportAction;
use Modules\Operations\Domain\Actions\ReportFaultAction;
use Modules\Operations\Domain\Actions\TriageFaultReportAction;
use Modules\Operations\Domain\Actions\VerifyWorkOrderAction;
use Modules\Operations\Domain\DataObjects\CreateCapitalProjectData;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Domain\DataObjects\IssuePartsToWorkOrderData;
use Modules\Operations\Domain\DataObjects\RaiseWorkOrderForHostelDamageData;
use Modules\Operations\Domain\DataObjects\RecordWorkOrderLabourData;
use Modules\Operations\Domain\DataObjects\ReportFaultData;
use Modules\Operations\Domain\DataObjects\TriageFaultReportData;
use Modules\Operations\Domain\Events\CriticalAssetServiceOverdue;
use Modules\Operations\Domain\Events\PreventiveWorkOrderGenerated;
use Modules\Operations\Domain\Events\SafetyFaultReported;
use Modules\Operations\Domain\Events\WorkOrderVerificationEscalated;
use Modules\Operations\Domain\Exceptions\WorkOrderRequiresBudgetLineException;
use Modules\Operations\Models\CapitalProject;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;
use Modules\Operations\Models\WorkOrder;
use Modules\People\Models\Staff;
use Modules\Stores\Domain\Actions\CreateBudgetAction;
use Modules\Stores\Domain\Actions\ReceiveStockAction;
use Modules\Stores\Domain\Actions\RegisterSupplierInvoiceAction;
use Modules\Stores\Domain\Actions\SubmitBudgetLineAction;
use Modules\Stores\Domain\DataObjects\CreateBudgetData;
use Modules\Stores\Domain\DataObjects\ReceiveStockData;
use Modules\Stores\Domain\DataObjects\RegisterSupplierInvoiceData;
use Modules\Stores\Domain\DataObjects\SubmitBudgetLineData;
use Modules\Stores\Models\AssetCategory;
use Modules\Stores\Models\FixedAsset;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreRequisition;
use Modules\Stores\Models\Supplier;
use Modules\Stores\Models\SupplierInvoice;

/**
 * @return array{school: School, year: AcademicYear, term: Term, user: User, user2: User, costCentre: CostCentre, store: Store, expenseAccount: Account}
 */
function ops02Fixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->create(['financial_state' => 'open', 'is_current' => true]);
    $user = User::factory()->create();
    $user2 = User::factory()->create();

    foreach (['journal', 'fault_report', 'work_order', 'store_requisition', 'fixed_asset', 'capital_project'] as $type) {
        app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
            schoolId: $school->id, documentType: $type, pattern: strtoupper(substr($type, 0, 3)).'/{SEQ:5}',
        ));
    }

    $costCentre = CostCentre::factory()->for($school)->create();
    $store = Store::factory()->for($school)->create();
    $expenseAccount = Account::factory()->for($school)->expense()->create(['code' => 'MAINT-EXP']);

    return compact('school', 'year', 'term', 'user', 'user2', 'costCentre', 'store', 'expenseAccount');
}

it('reports a fault with only location, description and severity, and fast-tracks a safety report (BR-OPS-02-001/002)', function (): void {
    Event::fake([SafetyFaultReported::class]);
    $f = ops02Fixture();

    $routine = app(ReportFaultAction::class)->execute(new ReportFaultData(
        schoolId: $f['school']->id, termId: $f['term']->id, location: 'Boys Hostel A',
        category: 'plumbing', description: 'Leaking tap.', severity: 'routine', reportedByUserId: $f['user']->id,
    ));
    expect($routine->status)->toBe('reported');
    Event::assertNotDispatched(SafetyFaultReported::class);

    $safety = app(ReportFaultAction::class)->execute(new ReportFaultData(
        schoolId: $f['school']->id, termId: $f['term']->id, location: 'Science Block',
        category: 'electrical', description: 'Exposed live wire near the corridor.', severity: 'routine',
        reportedByUserId: $f['user']->id, affectsSafety: true,
    ));
    Event::assertDispatched(SafetyFaultReported::class, fn ($event): bool => $event->report->is($safety));
});

it('triages a report into a work order that starts approved when under the threshold (BR-OPS-02-003)', function (): void {
    $f = ops02Fixture();
    $report = app(ReportFaultAction::class)->execute(new ReportFaultData(
        schoolId: $f['school']->id, termId: $f['term']->id, location: 'Dining Hall',
        category: 'plumbing', description: 'Blocked drain.', severity: 'urgent', reportedByUserId: $f['user']->id,
    ));

    $workOrder = app(TriageFaultReportAction::class)->execute($report->id, new TriageFaultReportData(
        academicYearId: $f['year']->id, workType: 'corrective', title: 'Unblock dining hall drain',
        priority: 'high', assignedTeam: 'in_house', costCentreId: $f['costCentre']->id, currency: 'USD',
        triagedByUserId: $f['user2']->id,
    ));

    expect($workOrder->status)->toBe('approved')
        ->and($workOrder->fault_report_id)->toBe($report->id)
        ->and($report->fresh()->status)->toBe('work_order_raised')
        ->and($report->fresh()->work_order_id)->toBe($workOrder->id);
});

it('marks a report duplicate or rejects it with a reason, and refuses to re-triage either (BR-OPS-02-003)', function (): void {
    $f = ops02Fixture();
    $original = app(ReportFaultAction::class)->execute(new ReportFaultData(
        schoolId: $f['school']->id, termId: $f['term']->id, location: 'Girls Hostel B',
        category: 'plumbing', description: 'Leaking tap.', severity: 'routine', reportedByUserId: $f['user']->id,
    ));
    $duplicate = app(ReportFaultAction::class)->execute(new ReportFaultData(
        schoolId: $f['school']->id, termId: $f['term']->id, location: 'Girls Hostel B',
        category: 'plumbing', description: 'Same leaking tap, reported again.', severity: 'routine', reportedByUserId: $f['user']->id,
    ));

    $markedDuplicate = app(MarkFaultReportDuplicateAction::class)->execute($duplicate->id, $original->id, $f['user2']->id);
    expect($markedDuplicate->status)->toBe('duplicate');

    expect(fn () => app(MarkFaultReportDuplicateAction::class)->execute($duplicate->id, $original->id, $f['user2']->id))
        ->toThrow(InvalidStateTransitionException::class);

    $rejected = app(RejectFaultReportAction::class)->execute($original->id, 'No fault found on inspection.', $f['user2']->id);
    expect($rejected->status)->toBe('rejected')
        ->and($rejected->triage_note)->toBe('No fault found on inspection.');

    expect(fn () => app(RejectFaultReportAction::class)->execute($original->id, '', $f['user2']->id))
        ->toThrow(InvalidStateTransitionException::class);
});

it('requires a budget line and checks FIN-11 availability once the estimate meets the approval threshold (BR-OPS-02-004)', function (): void {
    $f = ops02Fixture();

    $budget = app(CreateBudgetAction::class)->execute(new CreateBudgetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Maintenance Budget',
        budgetType: 'operating', periodBasis: 'annual', currency: 'USD', preparedByUserId: $f['user']->id,
    ));
    $line = app(SubmitBudgetLineAction::class)->execute(new SubmitBudgetLineData(
        budgetId: $budget->id, accountId: $f['expenseAccount']->id, costCentreId: $f['costCentre']->id,
        annualAmountMinor: 10000000, basisNote: 'Annual maintenance provision.',
    ));

    // Above the default 50,000 threshold, no budget line supplied — refused.
    expect(fn () => app(CreateWorkOrderAction::class)->execute(new CreateWorkOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        workType: 'corrective', title: 'Replace roof sheeting', description: 'Storm damage to the hall roof.',
        priority: 'high', assignedTeam: 'contractor', costCentreId: $f['costCentre']->id, currency: 'USD',
        raisedByUserId: $f['user']->id,
    ), 200000))->toThrow(WorkOrderRequiresBudgetLineException::class);

    $workOrder = app(CreateWorkOrderAction::class)->execute(new CreateWorkOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        workType: 'corrective', title: 'Replace roof sheeting', description: 'Storm damage to the hall roof.',
        priority: 'high', assignedTeam: 'contractor', costCentreId: $f['costCentre']->id, currency: 'USD',
        raisedByUserId: $f['user']->id, budgetLineId: $line->id,
    ), 200000);

    expect($workOrder->status)->toBe('pending_approval');

    $approved = app(ApproveWorkOrderAction::class)->execute($workOrder->id, $f['user2']->id);
    expect($approved->status)->toBe('approved')
        ->and($approved->approved_by)->toBe($f['user2']->id);

    expect(fn () => app(ApproveWorkOrderAction::class)->execute($workOrder->id, $f['user2']->id))
        ->toThrow(InvalidStateTransitionException::class);
});

it('issues parts through a real FIN-09 requisition against the work order cost centre, costing from the real FIFO issue (BR-OPS-02-005/AC-OPS-02-002)', function (): void {
    $f = ops02Fixture();
    $item = InventoryItem::factory()->for($f['school'])->create();

    app(ReceiveStockAction::class)->execute(new ReceiveStockData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        storeId: $f['store']->id, itemId: $item->id, quantity: 10, unitCostMinor: 500,
        currency: 'USD', receivedOn: now(), performedByUserId: $f['user']->id,
        contraAccountId: $f['expenseAccount']->id,
    ));

    $workOrder = app(CreateWorkOrderAction::class)->execute(new CreateWorkOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        workType: 'corrective', title: 'Fix leaking tap', description: 'Replace washer.',
        priority: 'normal', assignedTeam: 'in_house', costCentreId: $f['costCentre']->id, currency: 'USD',
        raisedByUserId: $f['user']->id,
    ));

    $updated = app(IssuePartsToWorkOrderAction::class)->execute(new IssuePartsToWorkOrderData(
        workOrderId: $workOrder->id, storeId: $f['store']->id, issuedByUserId: $f['user']->id,
        lines: [['itemId' => $item->id, 'quantity' => 4, 'unit' => $item->base_unit, 'description' => 'Tap washers']],
    ));

    expect($updated->parts_cost_minor)->toBe(2000)
        ->and($updated->total_cost_minor)->toBe(2000)
        ->and($updated->parts)->toHaveCount(1);

    $part = $updated->parts->first();
    expect($part->line_cost_minor)->toBe(2000)
        ->and($part->store_requisition_id)->not->toBeNull();

    $requisition = StoreRequisition::find($part->store_requisition_id);
    expect($requisition->cost_centre_id)->toBe($f['costCentre']->id)
        ->and($requisition->status)->toBe('issued');
});

it('costs labour at a supplied staff rate, or the configured default trade rate (BR-OPS-02-006)', function (): void {
    $f = ops02Fixture();
    $staff = Staff::factory()->for($f['school'])->create();

    $workOrder = app(CreateWorkOrderAction::class)->execute(new CreateWorkOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        workType: 'corrective', title: 'Fix leaking tap', description: 'Replace washer.',
        priority: 'normal', assignedTeam: 'in_house', costCentreId: $f['costCentre']->id, currency: 'USD',
        raisedByUserId: $f['user']->id,
    ));

    $withDefault = app(RecordWorkOrderLabourAction::class)->execute(new RecordWorkOrderLabourData(
        workOrderId: $workOrder->id, staffId: $staff->id, workDate: Carbon::now(), hours: 2.0,
        recordedByUserId: $f['user']->id,
    ));
    // Default rate is 500 minor units/hour (see OperationsServiceProvider).
    expect($withDefault->labour_cost_minor)->toBe(1000)
        ->and((float) $withDefault->labour_hours)->toBe(2.0);

    $withOwnRate = app(RecordWorkOrderLabourAction::class)->execute(new RecordWorkOrderLabourData(
        workOrderId: $workOrder->id, staffId: $staff->id, workDate: Carbon::now(), hours: 1.0,
        recordedByUserId: $f['user']->id, hourlyRateMinor: 2000,
    ));
    expect($withOwnRate->labour_cost_minor)->toBe(3000)
        ->and((float) $withOwnRate->labour_hours)->toBe(3.0)
        ->and($withOwnRate->total_cost_minor)->toBe(3000);
});

it('records contractor cost against a verified supplier invoice and sums total cost from labour, parts and contractor (BR-OPS-02-007/008)', function (): void {
    $f = ops02Fixture();
    $contractor = Supplier::factory()->for($f['school'])->create();

    $workOrder = app(CreateWorkOrderAction::class)->execute(new CreateWorkOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        workType: 'corrective', title: 'Repair perimeter fence', description: 'Storm damage.',
        priority: 'normal', assignedTeam: 'contractor', costCentreId: $f['costCentre']->id, currency: 'USD',
        raisedByUserId: $f['user']->id, contractorSupplierId: $contractor->id,
    ));

    $invoice = createOps02SupplierInvoice($f, $contractor);

    $updated = app(RecordContractorCostAction::class)->execute($workOrder->id, $invoice->id, 15000, $f['user']->id);

    expect($updated->contractor_cost_minor)->toBe(15000)
        ->and($updated->total_cost_minor)->toBe(15000);

    $otherContractor = Supplier::factory()->for($f['school'])->create();
    $mismatchedInvoice = createOps02SupplierInvoice($f, $otherContractor);

    expect(fn () => app(RecordContractorCostAction::class)->execute($workOrder->id, $mismatchedInvoice->id, 5000, $f['user']->id))
        ->toThrow(InvalidStateTransitionException::class);
});

it('requires completion notes, and a photo for safety-affecting work, then verifies only through the original requester (BR-OPS-02-011/012)', function (): void {
    $f = ops02Fixture();
    $report = app(ReportFaultAction::class)->execute(new ReportFaultData(
        schoolId: $f['school']->id, termId: $f['term']->id, location: 'Boys Hostel A',
        category: 'electrical', description: 'Exposed wire.', severity: 'emergency',
        reportedByUserId: $f['user']->id, affectsSafety: true,
    ));

    $workOrder = app(TriageFaultReportAction::class)->execute($report->id, new TriageFaultReportData(
        academicYearId: $f['year']->id, workType: 'corrective', title: 'Isolate exposed wire',
        priority: 'urgent', assignedTeam: 'in_house', costCentreId: $f['costCentre']->id, currency: 'USD',
        triagedByUserId: $f['user2']->id,
    ));

    expect(fn () => app(CompleteWorkOrderAction::class)->execute($workOrder->id, '', null, $f['user']->id))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CompleteWorkOrderAction::class)->execute($workOrder->id, 'Wire isolated and capped.', null, $f['user']->id))
        ->toThrow(ValidationException::class);

    $completed = app(CompleteWorkOrderAction::class)->execute($workOrder->id, 'Wire isolated and capped.', [1], $f['user']->id);
    expect($completed->status)->toBe('completed');

    // raised_by on the work order is the original fault reporter.
    expect(fn () => app(VerifyWorkOrderAction::class)->execute($workOrder->id, $f['user2']->id))
        ->toThrow(InvalidStateTransitionException::class);

    $verified = app(VerifyWorkOrderAction::class)->execute($workOrder->id, $f['user']->id);
    expect($verified->status)->toBe('verified')
        ->and($verified->verified_by)->toBe($f['user']->id);
});

it('escalates a completed work order left unverified past the configured window (BR-OPS-02-012)', function (): void {
    Event::fake([WorkOrderVerificationEscalated::class]);
    $f = ops02Fixture();

    $workOrder = app(CreateWorkOrderAction::class)->execute(new CreateWorkOrderData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id,
        workType: 'corrective', title: 'Fix leaking tap', description: 'Replace washer.',
        priority: 'normal', assignedTeam: 'in_house', costCentreId: $f['costCentre']->id, currency: 'USD',
        raisedByUserId: $f['user']->id,
    ));
    app(CompleteWorkOrderAction::class)->execute($workOrder->id, 'Washer replaced.', null, $f['user']->id);
    WorkOrder::where('id', $workOrder->id)->update(['completed_at' => now()->subDays(10)]);

    $escalated = app(CheckUnverifiedWorkOrdersAction::class)->execute($f['school']->id);

    expect($escalated)->toHaveCount(1);
    Event::assertDispatched(WorkOrderVerificationEscalated::class);
});

it('generates preventive work orders for calendar schedules within their lead time, and advances the next due date (BR-OPS-02-009/AC-OPS-02-003)', function (): void {
    Event::fake([PreventiveWorkOrderGenerated::class]);
    $f = ops02Fixture();
    $asset = MaintenanceAsset::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id]);
    $schedule = MaintenanceSchedule::factory()->calendar()->create([
        'school_id' => $f['school']->id, 'maintenance_asset_id' => $asset->id,
    ]);
    $dueDate = $schedule->next_due_on;

    $result = app(GeneratePreventiveWorkOrdersAction::class)->execute($f['school']->id, $f['user']->id);

    expect($result->generated)->toHaveCount(1);
    $workOrder = $result->generated->first();
    expect($workOrder->work_type)->toBe('preventive')
        ->and($workOrder->schedule_id)->toBe($schedule->id);

    $schedule->refresh();
    expect($schedule->last_generated_wo_id)->toBe($workOrder->id)
        ->and($schedule->next_due_on->toDateString())->toBe($dueDate->copy()->addDays(90)->toDateString());
    Event::assertDispatched(PreventiveWorkOrderGenerated::class);

    // A second run finds nothing due — the schedule already advanced.
    $second = app(GeneratePreventiveWorkOrdersAction::class)->execute($f['school']->id, $f['user']->id);
    expect($second->generated)->toHaveCount(0);
});

it('alerts on an overdue critical asset but not a normal one (BR-OPS-02-016/AC-OPS-02-005)', function (): void {
    Event::fake([CriticalAssetServiceOverdue::class]);
    $f = ops02Fixture();

    MaintenanceAsset::factory()->critical()->create([
        'school_id' => $f['school']->id, 'cost_centre_id' => $f['costCentre']->id,
        'next_service_due_on' => now()->subDays(3),
    ]);
    MaintenanceAsset::factory()->create([
        'school_id' => $f['school']->id, 'cost_centre_id' => $f['costCentre']->id,
        'next_service_due_on' => now()->subDays(3),
    ]);

    $overdue = app(CheckOverdueCriticalAssetAction::class)->execute($f['school']->id);

    expect($overdue)->toHaveCount(1);
    Event::assertDispatched(CriticalAssetServiceOverdue::class);
});

it('raises a work order from a hostel damage report and links back to it (BR-OPS-02-014)', function (): void {
    $f = ops02Fixture();
    $damage = HostelDamage::factory()->create([
        'school_id' => $f['school']->id, 'term_id' => $f['term']->id, 'estimated_cost_minor' => 8000,
    ]);

    $workOrder = app(RaiseWorkOrderForHostelDamageAction::class)->execute(new RaiseWorkOrderForHostelDamageData(
        hostelDamageId: $damage->id, academicYearId: $f['year']->id, workType: 'corrective',
        priority: 'normal', assignedTeam: 'in_house', costCentreId: $f['costCentre']->id, raisedByUserId: $f['user']->id,
    ));

    expect($damage->fresh()->work_order_id)->toBe($workOrder->id)
        ->and($workOrder->maintenance_asset_id)->toBeNull();

    expect(fn () => app(RaiseWorkOrderForHostelDamageAction::class)->execute(new RaiseWorkOrderForHostelDamageData(
        hostelDamageId: $damage->id, academicYearId: $f['year']->id, workType: 'corrective',
        priority: 'normal', assignedTeam: 'in_house', costCentreId: $f['costCentre']->id, raisedByUserId: $f['user']->id,
    )))->toThrow(InvalidStateTransitionException::class);
});

it('tracks a capital project and capitalises it to FIN-10 for real on completion when flagged (BR-OPS-02-015)', function (): void {
    $f = ops02Fixture();

    $assetAccount = Account::factory()->for($f['school'])->create(['code' => 'CAP-WIP-ASSET']);
    $accumDepAccount = Account::factory()->for($f['school'])->create(['code' => 'CAP-ACCDEP']);
    $depExpenseAccount = Account::factory()->for($f['school'])->expense()->create(['code' => 'CAP-DEPEXP']);
    $disposalAccount = Account::factory()->for($f['school'])->create(['code' => 'CAP-DISPOSAL']);
    $category = AssetCategory::factory()->create([
        'school_id' => $f['school']->id, 'code' => 'BLDG', 'asset_account_id' => $assetAccount->id,
        'accum_depreciation_account_id' => $accumDepAccount->id, 'depreciation_expense_account_id' => $depExpenseAccount->id,
        'disposal_account_id' => $disposalAccount->id,
    ]);

    $budget = app(CreateBudgetAction::class)->execute(new CreateBudgetData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, name: 'Capital Budget',
        budgetType: 'capital', periodBasis: 'annual', currency: 'USD', preparedByUserId: $f['user']->id,
    ));
    $line = app(SubmitBudgetLineAction::class)->execute(new SubmitBudgetLineData(
        budgetId: $budget->id, accountId: $f['expenseAccount']->id, costCentreId: $f['costCentre']->id,
        annualAmountMinor: 100000000, basisNote: 'New science block.',
    ));

    $project = app(CreateCapitalProjectAction::class)->execute(new CreateCapitalProjectData(
        schoolId: $f['school']->id, name: 'New Science Block', budgetMinor: 50000000, currency: 'USD',
        startsOn: Carbon::now(), createdByUserId: $f['user']->id, budgetLineId: $line->id,
        assetCategoryId: $category->id,
    ));
    expect($project->status)->toBe('planning');

    CapitalProject::where('id', $project->id)->update(['status' => 'in_progress', 'spent_minor' => 48000000]);

    $completed = app(CompleteCapitalProjectAction::class)->execute($project->id, $f['year']->id, $f['term']->id, $f['user2']->id);

    expect($completed->status)->toBe('completed')
        ->and($completed->actual_completion)->not->toBeNull();

    $asset = FixedAsset::where('school_id', $f['school']->id)->where('category_id', $category->id)->first();
    expect($asset)->not->toBeNull()
        ->and($asset->acquisition_cost_minor)->toBe(48000000)
        ->and($asset->acquisition_source)->toBe('capital_project');
});

/**
 * @param  array<string, mixed>  $f
 */
function createOps02SupplierInvoice(array $f, Supplier $supplier): SupplierInvoice
{
    return app(RegisterSupplierInvoiceAction::class)->execute(new RegisterSupplierInvoiceData(
        schoolId: $f['school']->id, academicYearId: $f['year']->id, termId: $f['term']->id, supplierId: $supplier->id,
        invoiceNumber: 'INV-'.fake()->unique()->numberBetween(1000, 9999), invoiceDate: now(), receivedOn: now(), dueDate: now()->addDays(30),
        currency: 'USD',
        lines: [['poLineId' => null, 'grnLineId' => null, 'description' => 'Contract works', 'quantity' => 1, 'unitPriceMinor' => 15000, 'taxCategory' => 'exempt', 'taxRatePercent' => 0, 'expenseAccountId' => $f['expenseAccount']->id, 'costCentreId' => $f['costCentre']->id]],
        registeredByUserId: $f['user']->id,
    ));
}

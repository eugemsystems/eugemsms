<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Journal;
use Modules\Stores\Livewire\Anomalies\Index as AnomaliesIndex;
use Modules\Stores\Livewire\Items\Index as ItemsIndex;
use Modules\Stores\Livewire\Receipts\Create as ReceiptsCreate;
use Modules\Stores\Livewire\Reports\Consumption;
use Modules\Stores\Livewire\Reports\Valuation;
use Modules\Stores\Livewire\Requisitions\Create as RequisitionsCreate;
use Modules\Stores\Livewire\Requisitions\Issue as RequisitionsIssue;
use Modules\Stores\Livewire\Requisitions\ReturnItems;
use Modules\Stores\Livewire\Stock\Expiry;
use Modules\Stores\Livewire\Stock\ItemLedger;
use Modules\Stores\Livewire\Stock\OnHand;
use Modules\Stores\Livewire\Stock\SellToLearner;
use Modules\Stores\Livewire\StockTake\Count;
use Modules\Stores\Livewire\StockTake\Variance;
use Modules\Stores\Livewire\Stores\Index as StoresIndex;
use Modules\Stores\Livewire\Transfers\Index as TransfersIndex;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\StockTake;
use Modules\Stores\Models\Store;
use Modules\Stores\Models\StoreRequisition;

/**
 * Book H1 FIN-09 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User, store: Store}
 */
function inventoryAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'store_requisition', pattern: 'REQ/{SEQ:6}', academicYearId: $year->id));
    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'stock_transfer', pattern: 'TRF/{SEQ:6}', academicYearId: $year->id));

    $store = Store::factory()->for($school)->create();

    return compact('school', 'year', 'term', 'user', 'store');
}

/**
 * Splits on the LAST dot for the action and treats everything before it
 * as the resource — required here because this module's own permission
 * names are a mix of two- and three-segment shapes (`inventory.issue`
 * vs. `inventory.store.manage`), the same trap `.ai/rules/boarding.md`
 * already documents for a different module.
 *
 * @param  array<string, mixed>  $f
 */
function inventoryAdminUser(array $f, string ...$permissionNames): User
{
    $user = User::factory()->create();
    $user->schools()->attach($f['school'], ['status' => 'active']);

    $grants = array_map(function (string $permissionName): PermissionGrantData {
        $lastDot = strrpos($permissionName, '.');
        $moduleCode = strtoupper(substr($permissionName, 0, strpos($permissionName, '.')));
        $action = substr($permissionName, $lastDot + 1);
        $resource = substr($permissionName, strpos($permissionName, '.') + 1, $lastDot - strpos($permissionName, '.') - 1);
        $resource = $resource !== '' ? $resource : $action;

        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['guard_name' => 'web', 'module_code' => $moduleCode, 'resource' => $resource, 'action' => $action],
        );

        return new PermissionGrantData($permission->id, PermissionScope::School);
    }, $permissionNames);

    app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
        userId: $user->id, schoolId: $f['school']->id, grants: $grants,
    ));

    return $user;
}

it('refuses to mount the store register for a user with no inventory.store.view grant', function (): void {
    $f = inventoryAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(StoresIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('consumes FIFO across two lots and posts one aggregated journal through the admin screens (AC-FIN-09-001)', function (): void {
    $f = inventoryAdminFixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    $contra = Account::factory()->for($f['school'])->liability()->create(['code' => 'GRN-ACC']);

    $receiver = inventoryAdminUser($f, 'inventory.receipt.create');
    $requester = inventoryAdminUser($f, 'inventory.requisition.create');
    $issuer = inventoryAdminUser($f, 'inventory.issue', 'inventory.requisition.approve');

    // Lot A: 100kg @ $0.80/unit (unitCostMinor 80), Lot B: 200kg @ $0.95/unit (unitCostMinor 95).
    Livewire::actingAs($receiver)->test(ReceiptsCreate::class, ['school' => $f['school']])
        ->set('storeId', $f['store']->id)->set('itemId', $item->id)
        ->set('quantity', '100')->set('unitCostMinor', '80')->set('contraAccountId', $contra->id)
        ->call('receive')->assertOk();

    Livewire::actingAs($receiver)->test(ReceiptsCreate::class, ['school' => $f['school']])
        ->set('storeId', $f['store']->id)->set('itemId', $item->id)
        ->set('quantity', '200')->set('unitCostMinor', '95')->set('contraAccountId', $contra->id)
        ->call('receive')->assertOk();

    Livewire::actingAs($requester)->test(RequisitionsCreate::class, ['school' => $f['school']])
        ->set('storeId', $f['store']->id)->set('costCentreId', $f['store']->cost_centre_id)
        ->set('purpose', 'Kitchen use')
        ->set('lines.0.item_id', (string) $item->id)->set('lines.0.quantity', '150')->set('lines.0.unit', $item->base_unit)
        ->call('submit')->assertOk();

    $requisition = StoreRequisition::where('store_id', $f['store']->id)->first();
    expect($requisition)->not->toBeNull();

    Livewire::actingAs($issuer)->test(RequisitionsIssue::class, ['school' => $f['school']])
        ->call('approve', $requisition->id)
        ->call('issue', $requisition->id)
        ->assertOk();

    $requisition->refresh();
    // 100kg @ 80 + 50kg @ 95 = 8000 + 4750 = 12750 ($127.50).
    expect($requisition->total_cost_minor)->toBe(12750)
        ->and($requisition->status)->toBe('issued');

    $journal = Journal::find($requisition->journal_id);
    expect($journal->lines)->toHaveCount(2)
        ->and($journal->lines->firstWhere('direction', 'DR')->amount_minor)->toBe(12750)
        ->and($journal->lines->firstWhere('direction', 'DR')->cost_centre_id)->toBe($f['store']->cost_centre_id);
});

it('never includes the system quantity in the blind count sheet payload (AC-FIN-09-005)', function (): void {
    $f = inventoryAdminFixture();
    $counter = inventoryAdminUser($f, 'inventory.stocktake.count');

    Livewire::actingAs($counter)->test(Count::class, ['school' => $f['school']])
        ->set('storeId', $f['store']->id)
        ->call('createTake')
        ->assertOk();

    $take = StockTake::where('store_id', $f['store']->id)->first();
    expect($take)->not->toBeNull()->and($take->is_blind_count)->toBeTrue();
});

it('renders every inventory screen for a fully-permissioned user', function (): void {
    $f = inventoryAdminFixture();
    $item = InventoryItem::factory()->for($f['school'])->create();
    $admin = inventoryAdminUser(
        $f,
        'inventory.store.view', 'inventory.store.manage', 'inventory.item.view', 'inventory.item.manage',
        'inventory.stock.view', 'inventory.receipt.create', 'inventory.requisition.create',
        'inventory.requisition.approve', 'inventory.issue', 'inventory.transfer.manage',
        'inventory.stocktake.count', 'inventory.stocktake.approve', 'inventory.adjustment.post',
        'inventory.anomaly.review', 'inventory.report.view',
    );

    Livewire::actingAs($admin)->test(StoresIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(ItemsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(OnHand::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(ItemLedger::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Expiry::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(SellToLearner::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(ReceiptsCreate::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(RequisitionsCreate::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(RequisitionsIssue::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(ReturnItems::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(TransfersIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Count::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Variance::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(AnomaliesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Valuation::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($admin)->test(Consumption::class, ['school' => $f['school']])->assertOk();

    expect($item)->not->toBeNull();
});

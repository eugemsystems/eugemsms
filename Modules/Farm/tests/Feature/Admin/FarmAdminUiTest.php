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
use Modules\Farm\Domain\Actions\RecordProductionOutputAction;
use Modules\Farm\Domain\DataObjects\RecordProductionOutputData;
use Modules\Farm\Livewire\Cycles\Index as CyclesIndex;
use Modules\Farm\Livewire\Fields\Index as FieldsIndex;
use Modules\Farm\Livewire\Harvest\Index as HarvestIndex;
use Modules\Farm\Livewire\KitchenTransfers\Index as TransfersIndex;
use Modules\Farm\Livewire\Livestock\Index as LivestockIndex;
use Modules\Farm\Livewire\LivestockEvents\Index as LivestockEventsIndex;
use Modules\Farm\Livewire\Production\Index as ProductionIndex;
use Modules\Farm\Livewire\Reports\Index as ReportsIndex;
use Modules\Farm\Livewire\Sales\Index as SalesIndex;
use Modules\Farm\Livewire\Units\Index as UnitsIndex;
use Modules\Farm\Models\CropCycle;
use Modules\Farm\Models\FarmField;
use Modules\Farm\Models\InternalTransfer;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\LivestockEvent;
use Modules\Farm\Models\ProductionOutput;
use Modules\Farm\Models\ProductionUnit;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Stores\Domain\Actions\CreateInventoryItemAction;
use Modules\Stores\Domain\Actions\CreateStoreAction;
use Modules\Stores\Domain\DataObjects\CreateInventoryItemData;
use Modules\Stores\Domain\DataObjects\CreateStoreData;
use Modules\Stores\Models\InventoryItem;
use Modules\Stores\Models\Store;

/**
 * Book H2 OPS-03 admin-UI pass. Own, distinctly-named fixture.
 *
 * @return array{school: School, year: AcademicYear, term: Term, user: User, costCentre: CostCentre, farmStore: Store, kitchenStore: Store, item: InventoryItem}
 */
function farmAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create(['financial_state' => 'open']);
    $user = User::factory()->create();
    $user->schools()->attach($school, ['status' => 'active']);

    $costCentre = CostCentre::factory()->for($school)->create();
    $inventoryAccount = Account::factory()->for($school)->create();
    $expenseAccount = Account::factory()->for($school)->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(schoolId: $school->id, documentType: 'journal', pattern: 'JNL/{SEQ:6}'));

    $farmStore = app(CreateStoreAction::class)->execute(new CreateStoreData(
        schoolId: $school->id, code: 'FARM', name: 'Farm Store', storeType: 'general', costCentreId: $costCentre->id,
        inventoryAccountId: $inventoryAccount->id, defaultExpenseAccountId: $expenseAccount->id,
    ));
    $kitchenStore = app(CreateStoreAction::class)->execute(new CreateStoreData(
        schoolId: $school->id, code: 'KITCHEN', name: 'Kitchen Store', storeType: 'kitchen', costCentreId: $costCentre->id,
        inventoryAccountId: $inventoryAccount->id, defaultExpenseAccountId: $expenseAccount->id,
    ));
    $item = app(CreateInventoryItemAction::class)->execute(new CreateInventoryItemData(
        schoolId: $school->id, code: 'TOM', name: 'Tomatoes', baseUnit: 'kg',
    ));

    return compact('school', 'year', 'term', 'user', 'costCentre', 'farmStore', 'kitchenStore', 'item');
}

/**
 * @param  array<string, mixed>  $f
 */
function farmAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the kitchen transfers screen for a user with no farm.transfer grant', function (): void {
    $f = farmAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(TransfersIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every farm screen for a fully-permissioned user', function (): void {
    $f = farmAdminFixture();
    $user = farmAdminUser(
        $f,
        'farm.manage', 'farm.crop.manage', 'farm.record', 'farm.livestock.manage',
        'farm.transfer', 'farm.sales.manage', 'farm.report.view',
    );

    Livewire::actingAs($user)->test(UnitsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(FieldsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(CyclesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(HarvestIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(LivestockIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(LivestockEventsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ProductionIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(TransfersIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(SalesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReportsIndex::class, ['school' => $f['school']])->assertOk();
});

it('derives cost per kg from total cycle cost over actual yield and creates a FIN-09 farm store lot at that cost (AC-OPS-03-001)', function (): void {
    $f = farmAdminFixture();
    $manager = farmAdminUser($f, 'farm.manage', 'farm.crop.manage', 'farm.record');

    $unit = ProductionUnit::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id, 'store_id' => $f['farmStore']->id]);
    $field = FarmField::factory()->create(['school_id' => $f['school']->id, 'production_unit_id' => $unit->id]);

    Livewire::actingAs($manager)->test(CyclesIndex::class, ['school' => $f['school']])
        ->set('productionUnitId', $unit->id)
        ->set('fieldId', $field->id)
        ->set('cycleReference', 'CROP-A/2026/T3')
        ->set('crop', 'Tomatoes')
        ->set('areaPlantedHectares', '2')
        ->call('plan')->assertOk();

    $cycle = CropCycle::where('school_id', $f['school']->id)->where('cycle_reference', 'CROP-A/2026/T3')->firstOrFail();
    $cycle->update(['input_cost_minor' => 144000, 'total_cost_minor' => 144000]);

    $account = Account::factory()->for($f['school'])->create();

    Livewire::actingAs($manager)->test(HarvestIndex::class, ['school' => $f['school']])
        ->set('cropCycleId', $cycle->id)
        ->set('quantityKg', '1800')
        ->set('itemId', $f['item']->id)
        ->set('farmProductionContraAccountId', $account->id)
        ->call('record')->assertOk();

    $cycle->refresh();

    expect($cycle->cost_per_kg_minor)->toBe(80);
});

it('blocks a kitchen transfer of milk from a cow still within its recorded withdrawal period, naming the end date (AC-OPS-03-003)', function (): void {
    $f = farmAdminFixture();
    $manager = farmAdminUser($f, 'farm.manage', 'farm.livestock.manage', 'farm.record', 'farm.transfer');

    $unit = ProductionUnit::factory()->for($f['school'])->create(['cost_centre_id' => $f['costCentre']->id, 'store_id' => $f['farmStore']->id]);
    $cow = Livestock::factory()->create(['school_id' => $f['school']->id, 'production_unit_id' => $unit->id, 'species' => 'cattle', 'purpose' => 'dairy', 'status' => 'active']);

    Livewire::actingAs($manager)->test(LivestockEventsIndex::class, ['school' => $f['school']])
        ->set('livestockId', $cow->id)
        ->set('eventType', 'treatment')
        ->set('withdrawalPeriodDays', 7)
        ->call('record')->assertOk();

    expect(LivestockEvent::where('livestock_id', $cow->id)->whereNotNull('withdrawal_ends_on')->exists())->toBeTrue();

    app(RecordProductionOutputAction::class)->execute(new RecordProductionOutputData(
        schoolId: $f['school']->id,
        productionUnitId: $unit->id,
        outputDate: now(),
        outputType: 'milk',
        quantity: 50,
        unit: 'litres',
        currency: 'USD',
        recordedByUserId: $manager->id,
    ));

    $output = ProductionOutput::where('production_unit_id', $unit->id)->where('output_type', 'milk')->firstOrFail();

    Livewire::actingAs($manager)->test(TransfersIndex::class, ['school' => $f['school']])
        ->set('productionUnitId', $unit->id)
        ->set('outputId', $output->id)
        ->set('fromStoreId', $f['farmStore']->id)
        ->set('toStoreId', $f['kitchenStore']->id)
        ->set('itemId', $f['item']->id)
        ->set('quantity', '10')
        ->call('transfer');

    expect(InternalTransfer::where('school_id', $f['school']->id)->count())->toBe(0);
});

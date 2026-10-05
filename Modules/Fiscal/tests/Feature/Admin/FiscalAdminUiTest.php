<?php

use App\Models\User;
use Illuminate\Support\Carbon;
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
use Modules\Fiscal\Domain\Actions\CreateFiscalisationRuleAction;
use Modules\Fiscal\Domain\Actions\RegisterFiscalDeviceAction;
use Modules\Fiscal\Domain\Actions\RouteReceiptForFiscalisationAction;
use Modules\Fiscal\Domain\DataObjects\CreateFiscalisationRuleData;
use Modules\Fiscal\Domain\DataObjects\RegisterFiscalDeviceData;
use Modules\Fiscal\Domain\DataObjects\RouteReceiptForFiscalisationData;
use Modules\Fiscal\Livewire\Audit\Index as AuditIndex;
use Modules\Fiscal\Livewire\Days\Index as DaysIndex;
use Modules\Fiscal\Livewire\Devices\Index as DevicesIndex;
use Modules\Fiscal\Livewire\Queue\Status as QueueStatus;
use Modules\Fiscal\Livewire\Receipts\Index as ReceiptsIndex;
use Modules\Fiscal\Livewire\Receipts\Retry as ReceiptsRetry;
use Modules\Fiscal\Livewire\Reconciliation\Index as ReconciliationIndex;
use Modules\Fiscal\Livewire\Reports\ZReports;
use Modules\Fiscal\Livewire\Rules\Index as RulesIndex;
use Modules\Fiscal\Models\FiscalDevice;
use Modules\Fiscal\Models\FiscalReceipt;

/**
 * Book H3 FIN-13 admin-UI pass. Own, distinctly-named fixture
 * (`fiscalAdminFixture`/`fiscalAdminUser`) — `fin13Fixture` already
 * exists in the sibling backend test file.
 *
 * @return array<string, mixed>
 */
function fiscalAdminFixture(): array
{
    $school = School::factory()->create(['base_currency' => 'USD']);
    SchoolContext::set($school);
    $year = AcademicYear::factory()->for($school)->current()->create();
    $term = Term::factory()->for($school)->for($year, 'academicYear')->current()->create();
    $user = User::factory()->create();

    app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
        schoolId: $school->id, documentType: 'journal', pattern: 'AFJ/{SEQ:6}',
    ));

    $device = app(RegisterFiscalDeviceAction::class)->execute(new RegisterFiscalDeviceData(
        schoolId: $school->id, deviceId: 'ADEV-001', deviceSerial: 'ASN-001', taxpayerName: 'Admin Test School',
        taxpayerTin: '9988776655', environment: 'sandbox', apiBaseUrl: 'https://sandbox.fdms.zimra.co.zw',
    ));

    app(CreateFiscalisationRuleAction::class)->execute(new CreateFiscalisationRuleData(
        schoolId: $school->id, ruleName: 'Tuckshop sales are standard-rated', sourceType: 'wallet_product',
        isFiscalisable: true, taxType: 'standard', taxRatePercent: '15',
        rationale: 'Tuckshop sales are a commercial supply.', reviewedByUserId: $user->id,
    ));

    return compact('school', 'year', 'term', 'user', 'device');
}

/**
 * @param  array<string, mixed>  $f
 */
function fiscalAdminUser(array $f, string ...$permissionNames): User
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

it('refuses to mount the devices screen for a user with no fiscal.device.manage grant', function (): void {
    $f = fiscalAdminFixture();
    $outsider = User::factory()->create();
    $outsider->schools()->attach($f['school'], ['status' => 'active']);

    Livewire::actingAs($outsider)->test(DevicesIndex::class, ['school' => $f['school']])
        ->assertForbidden();
});

it('renders every fiscal screen for a fully-permissioned user', function (): void {
    $f = fiscalAdminFixture();
    $user = fiscalAdminUser(
        $f,
        'fiscal.device.manage', 'fiscal.rules.manage', 'fiscal.day.manage', 'fiscal.view', 'fiscal.retry', 'fiscal.audit.view',
    );

    Livewire::actingAs($user)->test(DevicesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(RulesIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(DaysIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReceiptsIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReceiptsRetry::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(QueueStatus::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ZReports::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(ReconciliationIndex::class, ['school' => $f['school']])->assertOk();
    Livewire::actingAs($user)->test(AuditIndex::class, ['school' => $f['school']])->assertOk();
});

it('queues a receipt offline when FDMS is unreachable and drains it automatically once reachable again (AC-FIN-13-001/002)', function (): void {
    $f = fiscalAdminFixture();
    $user = fiscalAdminUser($f, 'fiscal.device.manage', 'fiscal.view', 'fiscal.retry');

    FiscalDevice::where('id', $f['device']->id)->update(['last_ping_status' => 'offline']);

    $receipt = app(RouteReceiptForFiscalisationAction::class)->execute(new RouteReceiptForFiscalisationData(
        schoolId: $f['school']->id, sourceType: 'wallet_product', sourceId: 1, receiptType: 'fiscal_invoice',
        currency: 'USD', invoiceNumber: 'INV-ADMIN-001', receiptDate: Carbon::now(),
        lines: [['source_identifier' => 'tuckshop', 'description' => 'Tuckshop sale', 'amount_minor' => 1000]],
        paymentMethods: ['cash'], performedByUserId: $f['user']->id,
    ));

    expect($receipt?->status)->toBe('offline_queued');

    Livewire::actingAs($user)->test(QueueStatus::class, ['school' => $f['school']])
        ->assertViewHas('depth', 1);

    FiscalDevice::where('id', $f['device']->id)->update(['last_ping_status' => 'online']);

    Livewire::actingAs($user)->test(QueueStatus::class, ['school' => $f['school']])
        ->call('drain');

    expect(FiscalReceipt::find($receipt->id)->status)->toBe('accepted');

    Livewire::actingAs($user)->test(QueueStatus::class, ['school' => $f['school']])
        ->assertViewHas('depth', 0);
});

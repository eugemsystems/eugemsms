<?php

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Install\ProvisionFirstSchoolAction;
use Modules\Core\Domain\Actions\Install\ProvisionFirstTenantAction;
use Modules\Core\Domain\DataObjects\Install\SchoolProvisionData;
use Modules\Core\Domain\DataObjects\Install\TenantProvisionData;

it('provisions the first tenant', function (): void {
    $tenant = (new ProvisionFirstTenantAction)->execute(new TenantProvisionData(
        name: 'Sunrise Group of Schools',
        slug: 'sunrise',
    ));

    expect($tenant->exists)->toBeTrue()
        ->and($tenant->status)->toBe('active')
        ->and($tenant->country)->toBe('ZW');
});

it('rejects a duplicate tenant slug', function (): void {
    (new ProvisionFirstTenantAction)->execute(new TenantProvisionData(name: 'A', slug: 'dup'));

    (new ProvisionFirstTenantAction)->execute(new TenantProvisionData(name: 'B', slug: 'dup'));
})->throws(ValidationException::class);

it('provisions the first school and attaches the admin as its primary user', function (): void {
    $tenant = (new ProvisionFirstTenantAction)->execute(new TenantProvisionData(name: 'Sunrise', slug: 'sunrise2'));
    $admin = User::factory()->create();

    $school = (new ProvisionFirstSchoolAction)->execute(new SchoolProvisionData(
        tenantId: $tenant->id,
        name: 'Sunrise Girls High',
        code: 'SGH',
        baseCurrency: 'USD',
        timezone: 'Africa/Harare',
        locale: 'en_ZW',
        adminUserId: $admin->id,
    ));

    expect($school->exists)->toBeTrue()
        ->and($school->tenant_id)->toBe($tenant->id)
        ->and($admin->isAssignedToSchool($school->id))->toBeTrue()
        ->and($admin->primarySchool()?->id)->toBe($school->id);
});

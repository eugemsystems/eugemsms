<?php

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Schools\CreateSchoolAction;
use Modules\Core\Domain\DataObjects\Schools\CreateSchoolData;
use Modules\Core\Models\Tenant;

it('creates a school under a tenant', function (): void {
    $tenant = Tenant::factory()->create();

    $school = (new CreateSchoolAction)->execute(new CreateSchoolData(
        tenantId: $tenant->id,
        code: 'SGH',
        name: 'Sunrise Girls High',
        category: 'private',
    ));

    expect($school->exists)->toBeTrue()
        ->and($school->tenant_id)->toBe($tenant->id)
        ->and($school->code)->toBe('SGH')
        ->and($school->status)->toBe('active');
});

it('rejects a duplicate code within the same tenant', function (): void {
    $tenant = Tenant::factory()->create();

    (new CreateSchoolAction)->execute(new CreateSchoolData($tenant->id, 'DUP', 'A', 'private'));
    (new CreateSchoolAction)->execute(new CreateSchoolData($tenant->id, 'DUP', 'B', 'private'));
})->throws(ValidationException::class);

it('allows the same code across two different tenants', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $schoolA = (new CreateSchoolAction)->execute(new CreateSchoolData($tenantA->id, 'SAME', 'A', 'private'));
    $schoolB = (new CreateSchoolAction)->execute(new CreateSchoolData($tenantB->id, 'SAME', 'B', 'private'));

    expect($schoolA->code)->toBe('SAME')->and($schoolB->code)->toBe('SAME');
});

it('rejects a code that is not uppercase alphanumeric', function (): void {
    $tenant = Tenant::factory()->create();

    (new CreateSchoolAction)->execute(new CreateSchoolData($tenant->id, 'bad-code!', 'A', 'private'));
})->throws(ValidationException::class);

it('rejects a centre number already used by another school system-wide', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    (new CreateSchoolAction)->execute(new CreateSchoolData(
        $tenantA->id, 'AAA', 'A', 'private', centreNumber: '12345',
    ));

    (new CreateSchoolAction)->execute(new CreateSchoolData(
        $tenantB->id, 'BBB', 'B', 'private', centreNumber: '12345',
    ));
})->throws(ValidationException::class);

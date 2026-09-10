<?php

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Schools\CreateTenantAction;
use Modules\Core\Domain\DataObjects\Schools\CreateTenantData;

it('creates a tenant in trial status', function (): void {
    $tenant = (new CreateTenantAction)->execute(new CreateTenantData(
        name: 'Sunrise Group of Schools',
        slug: 'sunrise-group',
        type: 'trust',
        contactEmail: 'admin@sunrise.example',
    ));

    expect($tenant->exists)->toBeTrue()
        ->and($tenant->status)->toBe('trial')
        ->and($tenant->type)->toBe('trust')
        ->and($tenant->country)->toBe('ZW');
});

it('rejects a duplicate slug', function (): void {
    (new CreateTenantAction)->execute(new CreateTenantData(name: 'A', slug: 'dup-slug'));

    (new CreateTenantAction)->execute(new CreateTenantData(name: 'B', slug: 'dup-slug'));
})->throws(ValidationException::class);

it('rejects an invalid tenant type', function (): void {
    (new CreateTenantAction)->execute(new CreateTenantData(name: 'A', slug: 'a-slug', type: 'not-a-real-type'));
})->throws(ValidationException::class);

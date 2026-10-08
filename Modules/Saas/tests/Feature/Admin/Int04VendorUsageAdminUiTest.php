<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Intelligence\Models\ApiUsageLog;
use Modules\Saas\Livewire\Vendor\Usage\Index as VendorUsage;

/**
 * Book J INT-04 §5/BR-INT-04-011 — the vendor's own aggregate view of
 * third-party API usage, closing the gap `Integrations\Usage\Dashboard`'s
 * own docblock flagged as school-scoped only. Own, distinctly-named
 * helper, mirroring `saa02AdminVendor()`.
 */
function int04AdminVendor(): User
{
    return User::factory()->create(['user_type' => UserType::Vendor, 'two_factor_confirmed_at' => now()]);
}

it('refuses a school-level user at the vendor usage screen', function (): void {
    $school = School::factory()->create();
    SchoolContext::set($school);
    $schoolAdmin = User::factory()->create(['tenant_id' => $school->tenant_id]);

    Livewire::actingAs($schoolAdmin)->test(VendorUsage::class)->assertForbidden();
});

it('aggregates API usage across every tenant for the vendor, by tenant and platform-wide', function (): void {
    $vendor = int04AdminVendor();

    $tenantA = Tenant::factory()->create(['name' => 'Acme Schools']);
    $schoolA = School::factory()->for($tenantA)->create(['name' => 'Acme Primary']);
    $tenantB = Tenant::factory()->create(['name' => 'Beacon Trust']);
    $schoolB = School::factory()->for($tenantB)->create(['name' => 'Beacon College']);

    ApiUsageLog::factory()->create(['school_id' => $schoolA->id, 'status_code' => 200, 'duration_ms' => 100, 'occurred_at' => now()->subHour()]);
    ApiUsageLog::factory()->create(['school_id' => $schoolA->id, 'status_code' => 500, 'duration_ms' => 300, 'occurred_at' => now()->subHour()]);
    ApiUsageLog::factory()->create(['school_id' => $schoolB->id, 'status_code' => 200, 'duration_ms' => 50, 'occurred_at' => now()->subHour()]);

    // Outside the default 7-day window — excluded from every total.
    ApiUsageLog::factory()->create(['school_id' => $schoolA->id, 'status_code' => 200, 'duration_ms' => 999, 'occurred_at' => now()->subDays(10)]);

    Livewire::actingAs($vendor)->test(VendorUsage::class)
        ->assertSee('Acme Schools')->assertSee('Acme Primary')
        ->assertSee('Beacon Trust')->assertSee('Beacon College')
        ->assertSee('150'); // platform-wide avg latency across the 3 in-window calls: (100+300+50)/3
});

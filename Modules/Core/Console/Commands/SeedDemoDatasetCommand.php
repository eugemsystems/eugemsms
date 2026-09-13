<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Schools\CreateSchoolAction;
use Modules\Core\Domain\Actions\Schools\CreateTenantAction;
use Modules\Core\Domain\Actions\Sessions\CreateAcademicYearAction;
use Modules\Core\Domain\DataObjects\Schools\CreateSchoolData;
use Modules\Core\Domain\DataObjects\Schools\CreateTenantData;
use Modules\Core\Domain\DataObjects\Sessions\CreateYearData;
use Modules\Core\Models\Tenant;

/**
 * `php artisan serp:seed:demo-dataset` (Book A Acceptance Gate,
 * Functional gate: "Two tenants, four schools, and three academic
 * years with full term structures exist in the demo dataset").
 *
 * Distinct from `serp:seed:zimbabwe`, which applies baseline packs
 * (chart of accounts, grading, roles) to a school that already exists
 * — this command creates the tenants/schools/years themselves, purely
 * through the same Actions the admin UI (`Schools\Index`,
 * `Sessions\Years`/`YearWizard`) already calls, so the resulting rows
 * are indistinguishable from ones an admin created by hand.
 *
 * Idempotent by tenant slug: re-running this is a safe no-op for any
 * tenant that already exists (each tenant is entirely skipped, not
 * partially re-created), so it's safe to run again after, say, a
 * `migrate:fresh` without needing to track whether it already ran.
 */
final class SeedDemoDatasetCommand extends Command
{
    protected $signature = 'serp:seed:demo-dataset';

    protected $description = 'Seed two tenants, four schools, and three full academic years per school for demos and manual QA.';

    /**
     * @var array<int, array{tenant: array{name: string, slug: string}, schools: array<int, array{code: string, name: string}>}>
     */
    private const array PLAN = [
        [
            'tenant' => ['name' => 'Eugem Education Trust', 'slug' => 'eugem-education-trust'],
            'schools' => [
                ['code' => 'EUGHIGH', 'name' => 'Eugem High School'],
                ['code' => 'EUGPRIM', 'name' => 'Eugem Primary School'],
            ],
        ],
        [
            'tenant' => ['name' => 'Mashonaland Council Schools', 'slug' => 'mashonaland-council-schools'],
            'schools' => [
                ['code' => 'MCSHIGH', 'name' => 'Mashonaland Council High School'],
                ['code' => 'MCSPRIM', 'name' => 'Mashonaland Council Primary School'],
            ],
        ],
    ];

    public function handle(
        CreateTenantAction $createTenant,
        CreateSchoolAction $createSchool,
        CreateAcademicYearAction $createYear,
    ): int {
        $currentCalendarYear = (int) Carbon::now()->format('Y');

        foreach (self::PLAN as $entry) {
            if (Tenant::where('slug', $entry['tenant']['slug'])->exists()) {
                $this->components->warn("Tenant [{$entry['tenant']['slug']}] already exists — skipped.");

                continue;
            }

            $tenant = $createTenant->execute(new CreateTenantData(
                name: $entry['tenant']['name'],
                slug: $entry['tenant']['slug'],
            ));
            $this->components->info("Created tenant [{$tenant->name}].");

            foreach ($entry['schools'] as $schoolPlan) {
                $school = $createSchool->execute(new CreateSchoolData(
                    tenantId: $tenant->id,
                    code: $schoolPlan['code'],
                    name: $schoolPlan['name'],
                    category: 'private',
                ));
                $this->components->info("  Created school [{$school->name}] ({$school->code}).");

                for ($offset = -1; $offset <= 1; $offset++) {
                    $yearNumber = $currentCalendarYear + $offset;

                    $year = $createYear->execute(new CreateYearData(
                        schoolId: $school->id,
                        name: (string) $yearNumber,
                        startsOn: Carbon::create($yearNumber, 1, 15),
                        endsOn: Carbon::create($yearNumber, 12, 10),
                        generateThreeTerms: true,
                    ));
                    $this->components->info("    Created academic year [{$year->name}] with 3 terms.");
                }
            }
        }

        return self::SUCCESS;
    }
}

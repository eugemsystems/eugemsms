<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

use Illuminate\Support\Str;
use Modules\Core\Domain\Contracts\Install\SeedPack;
use Modules\Core\Domain\Contracts\Install\SeedPackOutcome;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * Book A CORE-05 §3, Volume 1 §3.3's seeded role-template catalogue.
 * These are system templates (`school_id = null`, `is_system = true`) —
 * global, not per-school — so this pack is idempotent across every
 * school it runs for: the first school to install seeds the catalogue,
 * every later school's run of the same pack no-ops. Permission grants
 * are not attached here: almost every permission in the catalogue
 * belongs to a module that doesn't exist yet, so a role template ships
 * with no permissions until its owning modules register them — the
 * same "code owns the list" deferral as `RolloverHandlerRegistry`'s
 * pending handlers.
 */
final class RoleSeedPack implements SeedPack
{
    public function code(): string
    {
        return 'roles';
    }

    public function label(): string
    {
        return 'Roles';
    }

    public function description(): string
    {
        return 'The seeded system role templates (Volume 1 §3.3), ready to clone and grant permissions to.';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(School $school): SeedPackOutcome
    {
        $created = 0;

        foreach ($this->catalogue() as $category => $names) {
            foreach ($names as $name) {
                $slug = Str::slug($name, '_');

                if (Role::where('name', $slug)->whereNull('school_id')->exists()) {
                    continue;
                }

                Role::create([
                    'school_id' => null,
                    'name' => $slug,
                    'display_name' => $name,
                    'guard_name' => 'web',
                    'is_system' => true,
                    'is_vendor_only' => in_array($name, ['Super Admin', 'Support Engineer'], true),
                    'category' => $category,
                ]);

                $created++;
            }
        }

        return new SeedPackOutcome(
            packCode: $this->code(),
            ran: $created > 0,
            message: $created > 0 ? "Created {$created} system role templates." : 'System role templates already exist.',
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function catalogue(): array
    {
        return [
            'platform' => ['Super Admin', 'Support Engineer'],
            'executive' => ['Group Director', 'Headmaster/Headmistress', 'Deputy Head', 'Senior Master/Mistress'],
            'academic' => ['Head of Department', 'Senior Teacher', 'Class Teacher', 'Subject Teacher', 'Exams Officer', 'Librarian'],
            'administrative' => ['School Administrator', 'Registrar', 'Admissions Officer', 'Secretary'],
            'finance' => ['Bursar', 'Assistant Bursar', 'Cashier', 'Accounts Clerk', 'Procurement Officer', 'Storekeeper', 'Auditor'],
            'boarding' => ['Boarding Master/Mistress', 'Housemaster', 'Matron', 'Warden', 'Prefect'],
            'welfare' => ['School Nurse', 'Counsellor', 'Chaplain', 'Safeguarding Lead'],
            'operations' => ['Transport Manager', 'Driver', 'Maintenance Officer', 'Farm Manager', 'Kitchen Manager', 'Security/Gatekeeper'],
            'external' => ['Parent/Guardian', 'Student', 'Alumni', 'Supplier'],
        ];
    }
}

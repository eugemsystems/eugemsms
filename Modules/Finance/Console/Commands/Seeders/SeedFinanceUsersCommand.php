<?php

declare(strict_types=1);

namespace Modules\Finance\Console\Commands\Seeders;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Actions\Auth\UpdateUserPermissionsAction;
use Modules\Core\Domain\Actions\Schools\AssignUserToSchoolAction;
use Modules\Core\Domain\DataObjects\Auth\PermissionGrantData;
use Modules\Core\Domain\DataObjects\Auth\UserPermissionData;
use Modules\Core\Domain\DataObjects\Schools\AssignUserData;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\ZimbabweanNames;
use Modules\Core\Models\Permission;
use Modules\Core\Models\School;

/**
 * Step 2 of the `serp:seed:finance-*` suite. Creates one Finance test
 * user per real-world role, each holding only the permissions that
 * role would actually have — deliberately narrow, not one superuser —
 * so every two-person approval gate built across FIN-01 through FIN-04
 * (manual journal approval, billing approve/commit, ad hoc charge and
 * credit note approval, waiver approval, payment plan approval, till
 * variance sign-off) can be exercised by logging in as the requester
 * and then as a genuinely different approver, exactly as the domain
 * layer requires (an approver who is the same user as the requester is
 * refused everywhere this is enforced).
 *
 * A `Bursar` account holding every Finance permission is also created,
 * for convenience when a scenario doesn't call for testing a
 * permission boundary.
 */
final class SeedFinanceUsersCommand extends Command
{
    protected $signature = 'serp:seed:finance-users {--code=FINDEMO : The demo school code from serp:seed:finance-school-setup}';

    protected $description = 'Seed Finance test users (bursar, cashiers, supervisor, approvers) with realistic, role-scoped permissions.';

    /**
     * @var array<string, array<int, string>>
     */
    private const array ROLES = [
        'Bursar' => ['*'],
        'Accountant' => [
            'account.view', 'account.manage', 'cost_centre.view', 'cost_centre.manage',
            'journal.view', 'journal.create_manual', 'posting_rule.view', 'posting_rule.manage',
            'report.trial_balance', 'integrity.view', 'currency.manage', 'rate.view',
            'rate.capture', 'fx.revalue',
        ],
        'Cashier One' => ['till.operate', 'receipt.create', 'receipt.view'],
        'Cashier Two' => ['till.operate', 'receipt.create', 'receipt.view'],
        'Till Supervisor' => [
            'till.supervise', 'till.view', 'suspense.view', 'suspense.manage', 'report.collections',
        ],
        'Debt Collector' => [
            'report.debtors', 'debtor.manage', 'reminder.manage', 'payment_plan.create', 'invoice.view',
        ],
        'Fees Clerk' => [
            'fee_component.manage', 'fee_structure.view', 'fee_structure.manage', 'billing.view',
            'billing.run', 'ad_hoc.create', 'invoice.view', 'invoice.issue', 'invoice.void',
            'credit_note.create', 'waiver.request',
        ],
        'Finance Approver' => [
            'journal.approve', 'journal.reverse', 'fee_structure.approve', 'billing.approve',
            'billing.commit', 'ad_hoc.approve', 'credit_note.approve', 'waiver.approve',
            'payment_plan.approve', 'rate.approve', 'receipt.reallocate', 'invoice.view',
        ],
    ];

    public function handle(): int
    {
        $code = mb_strtoupper((string) $this->option('code'));
        $school = School::withoutGlobalScopes()->where('code', $code)->first();

        if ($school === null) {
            $this->components->error("No school with code [{$code}] found — run serp:seed:finance-school-setup first.");

            return self::FAILURE;
        }

        $allPermissionIds = Permission::where('module_code', 'FINANCE')->pluck('id', 'name');

        if ($allPermissionIds->isEmpty()) {
            $this->components->error('No FINANCE permissions are registered yet — run php artisan serp:sync-permissions first.');

            return self::FAILURE;
        }

        $bootstrap = User::firstWhere('email', "seed-bootstrap-{$code}@example.zw");

        if ($bootstrap === null) {
            $this->components->error("The bootstrap user for [{$code}] is missing — re-run serp:seed:finance-school-setup.");

            return self::FAILURE;
        }

        foreach (self::ROLES as $roleName => $permissionNames) {
            $this->components->task($roleName, function () use ($school, $bootstrap, $allPermissionIds, $roleName, $permissionNames): bool {
                $slug = mb_strtolower(str_replace(' ', '.', $roleName));
                $firstName = ZimbabweanNames::firstName(random_int(0, 1) === 0 ? 'male' : 'female');
                $lastName = ZimbabweanNames::surname();

                $user = User::firstOrCreate(
                    ['email' => "{$slug}@nyaradzo.example.zw"],
                    [
                        'name' => "{$firstName} {$lastName} ({$roleName})",
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ],
                );

                app(AssignUserToSchoolAction::class)->execute(new AssignUserData(
                    schoolId: $school->id,
                    userId: $user->id,
                    assignedByUserId: $bootstrap->id,
                ));

                $names = $permissionNames === ['*'] ? $allPermissionIds->keys()->all() : $permissionNames;

                $grants = collect($names)
                    ->map(fn (string $name): ?PermissionGrantData => isset($allPermissionIds[$name])
                        ? new PermissionGrantData($allPermissionIds[$name], PermissionScope::School)
                        : null)
                    ->filter()
                    ->values()
                    ->all();

                app(UpdateUserPermissionsAction::class)->execute(new UserPermissionData(
                    userId: $user->id,
                    schoolId: $school->id,
                    grants: $grants,
                    updatedByUserId: $bootstrap->id,
                ));

                return true;
            });
        }

        $this->components->info('Every test user\'s password is "password". Run serp:seed:finance-ledger next.');
        $this->newLine();
        $this->table(['Role', 'Email'], collect(self::ROLES)->keys()->map(fn (string $role): array => [
            $role, mb_strtolower(str_replace(' ', '.', $role)).'@nyaradzo.example.zw',
        ])->all());

        return self::SUCCESS;
    }
}

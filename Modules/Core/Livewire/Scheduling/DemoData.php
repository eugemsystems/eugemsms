<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Scheduling;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * `Core\Scheduling\DemoData` — a directory of every `serp:seed:*` demo/
 * test-data command in the app, each runnable from a button instead of
 * needing the exact artisan command name memorised. Currently lists
 * only the Book B `serp:seed:finance-*` suite; a later module's own
 * seed commands are added to `self::SEEDERS` the same way, not by
 * building a generic cross-module registry for a single current use
 * case.
 *
 * Tenant-wide with no `{school}` of its own, authenticated-only for
 * now — the same accepted interim state `Scheduling\Tasks`/`Health`/
 * `Maintenance` are already in (see `Tasks`'s own docblock). Actually
 * *running* a seeder is gated harder than just viewing this list,
 * though: real seed commands that write directly to the database
 * (rather than just reporting on it, like every other System screen)
 * are blocked outside local/staging so nobody fat-fingers demo data
 * into a live production database from a browser button.
 */
#[Title('Demo data')]
#[Layout('layouts.app')]
final class DemoData extends Component
{
    use Toasts;

    public string $code = 'FINDEMO';

    public int $students = 130;

    public ?string $lastRanKey = null;

    public ?string $lastOutput = null;

    public bool $lastRanOk = false;

    /**
     * @var array<int, array{key: string, name: string, description: string, command: string}>
     */
    private const array SEEDERS = [
        [
            'key' => 'finance-school-setup',
            'name' => 'Finance: School setup',
            'description' => 'A whole demo school from scratch — tenant, academic year, terms, sections/grades/classes, chart of accounts, cost centres, currencies, numbering series, two tills, and a Zimbabwean student/guardian roll. Run this first.',
            'command' => 'serp:seed:finance-school-setup',
        ],
        [
            'key' => 'finance-users',
            'name' => 'Finance: Users & approvals',
            'description' => 'One test user per Finance role (Bursar, Accountant, Cashier One/Two, Till Supervisor, Debt Collector, Fees Clerk, Finance Approver), each holding only the permissions that role would really have — so every two-person approval gate can be tested with a genuinely different requester and approver. Password for every user is "password".',
            'command' => 'serp:seed:finance-users',
        ],
        [
            'key' => 'finance-ledger',
            'name' => 'Finance: Ledger',
            'description' => 'Manual journals in every state (draft awaiting approval, approved, reversed) and a posting rule.',
            'command' => 'serp:seed:finance-ledger',
        ],
        [
            'key' => 'finance-currency',
            'name' => 'Finance: Currency',
            'description' => 'A month of USD to ZWG exchange rate history (active, one pending approval, one rejected) and an FX revaluation run.',
            'command' => 'serp:seed:finance-currency',
        ],
        [
            'key' => 'finance-fees',
            'name' => 'Finance: Fees & billing',
            'description' => 'The fee component catalogue, one fee structure per section/residency combination, a few ad hoc charges, and a fully computed, approved, and committed billing run — real invoices for every seeded student.',
            'command' => 'serp:seed:finance-fees',
        ],
        [
            'key' => 'finance-debtors',
            'name' => 'Finance: Debtors',
            'description' => 'Credit notes, waivers (approved/rejected/pending), payment plans (including one deliberately breached), a reminder ladder, and debtor chase notes — raised against the invoices the fees seeder created.',
            'command' => 'serp:seed:finance-debtors',
        ],
        [
            'key' => 'finance-till',
            'name' => 'Finance: Till & receipting',
            'description' => 'Two till sessions covering every tender type, a partial payment, an overpayment (credit balance), an uncleared-then-cleared cheque, an unidentified deposit resolved through suspense, a clean cash-up, and a cash-up variance closed by the Till Supervisor.',
            'command' => 'serp:seed:finance-till',
        ],
        [
            'key' => 'finance-all',
            'name' => 'Finance: Run all (in order)',
            'description' => 'Runs every Finance seeder above in the only order that works — each step depends on rows the previous one created. Use this for a one-click complete dataset.',
            'command' => 'serp:seed:finance-all',
        ],
    ];

    public function mount(): void
    {
        abort_unless(app()->environment(['local', 'staging', 'testing']), 403, __('Demo data seeding is disabled in this environment.'));
    }

    public function run(string $key): void
    {
        $seeder = collect(self::SEEDERS)->firstWhere('key', $key);

        abort_if($seeder === null, 404);

        $this->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'students' => ['required', 'integer', 'min:10', 'max:1000'],
        ]);

        $arguments = ['--code' => $this->code];

        if (in_array($key, ['finance-school-setup', 'finance-all'], true)) {
            $arguments['--students'] = $this->students;
        }

        $exitCode = Artisan::call($seeder['command'], $arguments);

        $this->lastRanKey = $key;
        $this->lastOutput = trim(Artisan::output());
        $this->lastRanOk = $exitCode === 0;

        $this->toast(
            $this->lastRanOk
                ? __(':name finished.', ['name' => $seeder['name']])
                : __(':name failed — see the output below.', ['name' => $seeder['name']]),
            $this->lastRanOk ? 'success' : 'danger',
        );
    }

    public function render(): View
    {
        return view('core::scheduling.demo-data', [
            'seeders' => self::SEEDERS,
        ]);
    }
}

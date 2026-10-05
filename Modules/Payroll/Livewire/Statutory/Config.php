<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Statutory;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Domain\Actions\ConfirmStatutoryConfigurationAction;
use Modules\Payroll\Domain\Actions\CreateStatutoryConfigurationAction;
use Modules\Payroll\Domain\DataObjects\ConfirmStatutoryConfigurationData;
use Modules\Payroll\Domain\DataObjects\CreateStatutoryConfigurationData;
use Modules\Payroll\Models\StatutoryConfiguration;

/**
 * `Payroll\Statutory\Config` (Book H3 PPL-05 §0.1/§2 ⭐,
 * `payroll.statutory.manage` to create/confirm, `payroll.view` to
 * view). The ONLY place a PAYE band, AIDS Levy rate, NSSA POBS/APWCS
 * rate, ZIMDEF rate or NEC due is ever entered into this system — as
 * a new, effective-dated, versioned row in `statutory_configurations`,
 * never a constant anywhere in this screen or its view (CLAUDE.md's
 * own non-negotiable rule, BR-PPL-05-001). Creating a new row never
 * edits an old one (`CreateStatutoryConfigurationAction` supersedes,
 * `StatutoryConfigResolver` still resolves a superseded row for dates
 * within its own range — BR-PPL-05-004) and a row flagged
 * `requires_confirmation` blocks every payroll run until confirmed
 * here (BR-PPL-05-002, AC-PPL-05-001) — the banner below is read live
 * every render, the same `requires_confirmation` pattern
 * `Academic\Curriculum\Frameworks` already established for this
 * codebase.
 */
#[Title('Statutory configuration')]
#[Layout('layouts.app')]
final class Config extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $configType = 'paye_bands';

    public string $currency = 'USD';

    public string $effectiveFrom = '';

    public string $configurationJson = '';

    public string $sourceReference = '';

    public bool $requiresConfirmation = true;

    /**
     * @var array<string, string>
     */
    public array $shapeHints = [
        'paye_bands' => '{"bands":[{"from_minor":0,"to_minor":10000,"rate":"0.00"},{"from_minor":10000,"to_minor":null,"rate":"0.20"}]}',
        'aids_levy' => '{"rate":"0.03"}',
        'nssa_pension' => '{"ceiling_minor":70000,"employee_rate":"0.045","employer_rate":"0.045"}',
        'nssa_apwcs' => '{"employer_rate":"0.01"}',
        'zimdef' => '{"rate":"0.01"}',
        'nec_dues' => '{"employee_rate":"0.01","employer_rate":"0.01"}',
        'withholding' => '{"rate":"0.10"}',
        'credits' => '{"amount_minor":0}',
    ];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.view');

        $this->effectiveFrom = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('payroll.statutory.manage');

        $this->validate([
            'configType' => ['required', 'in:paye_bands,aids_levy,nssa_pension,nssa_apwcs,zimdef,nec_dues,withholding,credits'],
            'currency' => ['required', 'in:USD,ZWG'],
            'effectiveFrom' => ['required', 'date'],
            'configurationJson' => ['required', 'string'],
        ]);

        $decoded = json_decode($this->configurationJson, true);

        if (! is_array($decoded)) {
            $this->addError('configurationJson', __('Configuration must be valid JSON.'));

            return;
        }

        app(CreateStatutoryConfigurationAction::class)->execute(new CreateStatutoryConfigurationData(
            configType: $this->configType,
            configuration: $decoded,
            effectiveFrom: Carbon::parse($this->effectiveFrom),
            createdByUserId: (int) Auth::id(),
            schoolId: $this->school->id,
            currency: in_array($this->configType, ['paye_bands', 'nssa_pension'], true) ? $this->currency : null,
            sourceReference: $this->sourceReference !== '' ? $this->sourceReference : null,
            requiresConfirmation: $this->requiresConfirmation,
        ));

        $this->reset(['configurationJson', 'sourceReference']);
        $this->toast(__('Statutory configuration saved.'));
    }

    public function confirm(int $statutoryConfigurationId): void
    {
        $this->authorizePermission('payroll.statutory.manage');

        app(ConfirmStatutoryConfigurationAction::class)->execute(new ConfirmStatutoryConfigurationData(
            statutoryConfigurationId: $statutoryConfigurationId,
            confirmedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Configuration confirmed — unblocked for payroll.'));
    }

    public function render(): View
    {
        $configs = StatutoryConfiguration::where(fn ($q) => $q->where('school_id', $this->school->id)->orWhereNull('school_id'))
            ->orderByDesc('effective_from')
            ->get();

        return view('payroll::statutory.config', [
            'configs' => $configs,
            'unconfirmed' => $configs->where('requires_confirmation', true)->whereNull('confirmed_at'),
        ]);
    }
}

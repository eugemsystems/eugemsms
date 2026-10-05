<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Vendor\Adoption;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Scopes\SchoolScope;
use Modules\Core\Models\School;
use Modules\Core\Models\Tenant;
use Modules\Saas\Domain\Actions\ListDormantEntitledModulesAction;
use Modules\Saas\Domain\Actions\RecordModuleAdoptionAction;
use Modules\Saas\Domain\Actions\RecordVendorConsoleActionAction;
use Modules\Saas\Livewire\Concerns\AuthorizesVendorConsole;
use Modules\Saas\Models\ModuleAdoptionScore;

/**
 * `Success\Adoption\Index` (Book J SAA-03 §5, vendor console). Adoption is
 * measured from real recorded activity, never self-reported
 * (BR-SAA-03-006): for each entitled module, the activity signal, its
 * count this month and whether that counts as actively used. Modules with
 * no activity for the sustained window surface as engagement
 * opportunities, not billing disputes (BR-SAA-03-007).
 */
#[Title('Adoption')]
#[Layout('saas::layouts.vendor')]
final class Index extends Component
{
    use AuthorizesVendorConsole;
    use Toasts;

    public ?int $tenantId = null;

    public ?int $schoolId = null;

    public string $periodMonth = '';

    public function mount(): void
    {
        $this->authorizeVendor();
        $this->periodMonth = now()->format('Y-m');
    }

    public function updatedTenantId(): void
    {
        $this->schoolId = null;
    }

    public function recompute(): void
    {
        $operator = $this->authorizeVendor();

        $school = $this->selectedSchool();

        if ($school === null || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->periodMonth) !== 1) {
            $this->toast(__('Choose a school and a valid month first.'), 'danger');

            return;
        }

        app(RecordModuleAdoptionAction::class)->execute($school->id, $this->periodMonth);

        app(RecordVendorConsoleActionAction::class)->execute($operator, 'adoption.recomputed', "Adoption recomputed for {$school->name}", $school->tenant_id, ['school_id' => $school->id, 'period' => $this->periodMonth]);

        $this->toast(__('Recomputed from recorded activity.'));
    }

    private function selectedSchool(): ?School
    {
        return $this->tenantId === null || $this->schoolId === null ? null : School::query()->where('tenant_id', $this->tenantId)->find($this->schoolId);
    }

    public function render(): View
    {
        $this->authorizeVendor();

        $school = $this->selectedSchool();
        $validPeriod = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->periodMonth) === 1;

        return view('saas::vendor.adoption', [
            'tenants' => Tenant::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'schools' => $this->tenantId === null ? collect() : School::query()->where('tenant_id', $this->tenantId)->orderBy('name')->get(['id', 'name']),
            'scores' => $school === null || ! $validPeriod ? collect() : ModuleAdoptionScore::query()->withoutGlobalScope(SchoolScope::class)
                ->where('school_id', $school->id)->where('period_month', $this->periodMonth)->orderBy('module_code')->get(),
            'dormant' => $school === null ? [] : app(ListDormantEntitledModulesAction::class)->execute($school->id),
        ]);
    }
}

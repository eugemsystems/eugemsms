<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Discounts;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\CreateDiscountSchemeAction;
use Modules\Finance\Domain\Actions\SetDiscountSchemeActiveAction;
use Modules\Finance\Domain\DataObjects\CreateDiscountSchemeData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\DiscountScheme;
use Modules\Finance\Models\FeeComponent;

/**
 * `Finance\Discounts\Schemes` (Book K FIN-07 §5,
 * `finance.discount_scheme.manage`). The catalogue of discount, bursary and
 * scholarship schemes. Only sibling and staff-child schemes can be
 * automatic — the only eligibility rules the billing run can evaluate — and
 * a sibling scheme carries its tier bands (nth child → percent). Every
 * value is validated by the Action; the contra account must be this
 * school's own postable account.
 */
#[Title('Discount schemes')]
#[Layout('layouts.app')]
final class Schemes extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $schemeType = 'individually_granted';

    public string $category = 'hardship';

    public string $calculationMethod = 'percentage';

    public string $defaultPercent = '';

    public string $defaultAmount = '';

    public string $currency = 'USD';

    public string $tierBands = '';

    /** @var array<int, int> */
    public array $componentIds = [];

    public bool $requiresMeans = false;

    public bool $requiresAcademic = false;

    public string $minimumAverage = '';

    public bool $requiresApproval = true;

    public bool $isSponsorFunded = false;

    public string $renewalFrequency = '';

    public ?int $contraAccountId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.discount_scheme.view');
    }

    public function create(): void
    {
        $this->authorizePermission('finance.discount_scheme.manage');
        $this->resetErrorBag();

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'contraAccountId' => ['required', 'integer'],
            'renewalFrequency' => ['nullable', 'in:termly,annual,once'],
        ]);

        $bands = $this->parseBands();

        if ($bands === false) {
            $this->addError('tierBands', __('Write each band as “2=10” — the child’s position, then the percentage — one per line.'));

            return;
        }

        try {
            app(CreateDiscountSchemeAction::class)->execute(new CreateDiscountSchemeData(
                schoolId: $this->school->id,
                code: strtoupper(trim($this->code)),
                name: trim($this->name),
                schemeType: $this->schemeType,
                category: $this->category,
                calculationMethod: $this->calculationMethod,
                contraAccountId: (int) $this->contraAccountId,
                appliesToComponents: $this->componentIds === [] ? null : array_map('intval', $this->componentIds),
                defaultPercent: $this->defaultPercent === '' ? null : $this->defaultPercent,
                defaultAmountMinor: $this->defaultAmount === '' ? null : (int) round((float) $this->defaultAmount * 100),
                currency: $this->defaultAmount === '' && $this->calculationMethod !== 'fixed_amount' ? null : strtoupper($this->currency),
                tierBands: $bands,
                requiresMeansAssessment: $this->requiresMeans,
                requiresAcademicThreshold: $this->requiresAcademic,
                minimumAveragePercent: $this->minimumAverage === '' ? null : $this->minimumAverage,
                requiresApproval: $this->requiresApproval,
                isSponsorFunded: $this->isSponsorFunded,
                renewalFrequency: $this->renewalFrequency === '' ? null : $this->renewalFrequency,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('code', $exception->getMessage());

            return;
        }

        $this->reset('code', 'name', 'defaultPercent', 'defaultAmount', 'tierBands', 'componentIds', 'minimumAverage', 'contraAccountId');
        $this->toast(__('Scheme created.'));
    }

    public function setActive(int $schemeId, bool $isActive): void
    {
        $this->authorizePermission('finance.discount_scheme.manage');

        app(SetDiscountSchemeActiveAction::class)->execute(DiscountScheme::query()->findOrFail($schemeId)->id, $isActive);

        $this->toast($isActive ? __('Scheme reopened.') : __('Scheme closed to new applications and grants.'));
    }

    /**
     * @return array<int, array{nth: int, percent: string}>|false|null
     */
    private function parseBands(): array|false|null
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $this->tierBands) ?: [])));

        if ($lines === []) {
            return null;
        }

        $bands = [];

        foreach ($lines as $line) {
            if (preg_match('/^(\d+)\s*=\s*(\d+(?:\.\d+)?)$/', $line, $match) !== 1) {
                return false;
            }

            $bands[] = ['nth' => (int) $match[1], 'percent' => $match[2]];
        }

        return $bands;
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('finance::discounts.schemes', [
            'schemes' => DiscountScheme::query()->orderByDesc('is_active')->orderBy('code')->limit(200)->get(),
            'accounts' => Account::query()->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name']),
            'components' => FeeComponent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canManage' => $user !== null && app(PermissionScopeResolver::class)->has($user, 'finance.discount_scheme.manage', PermissionScope::Own),
        ]);
    }
}

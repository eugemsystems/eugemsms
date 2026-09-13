<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Fees;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Finance\Domain\Actions\CountLearnersMatchingRulesAction;
use Modules\Finance\Domain\Actions\CreateFeeStructureAction;
use Modules\Finance\Domain\Actions\ReviseFeeStructureAction;
use Modules\Finance\Domain\DataObjects\CountLearnersMatchingRulesData;
use Modules\Finance\Domain\DataObjects\CreateFeeStructureData;
use Modules\Finance\Domain\DataObjects\ReviseFeeStructureData;
use Modules\Finance\Models\FeeComponent;
use Modules\Finance\Models\FeeStructure;

/**
 * `Finance\Fees\StructureBuilder` (Book B FIN-02 §7, `finance.fee_structure.manage`)
 * — rules panel, items panel, live match count. Two modes only, matching
 * the two Actions that actually exist: creating a brand-new structure
 * (`CreateFeeStructureAction`, version 1) when `$structure` is null, or
 * revising an existing one (`ReviseFeeStructureAction`, version n+1,
 * pre-filled from the current row) when it isn't — there is no "edit a
 * draft's rules in place" action, so even fixing a typo in a still-draft
 * structure is another revision. The match count is a manual "Preview
 * match count" action rather than recomputing on every keystroke: it
 * queries every active student in the school, which is too expensive to
 * run on each character typed into a rule value.
 */
#[Title('Fee structure builder')]
#[Layout('layouts.app')]
final class StructureBuilder extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $revisingStructureId = null;

    public string $name = '';

    public int $priority = 100;

    public ?int $academicYearId = null;

    public ?int $termId = null;

    /**
     * @var array<int, array{attribute: string, operator: string, value: string}>
     */
    public array $rules = [];

    /**
     * @var array<int, array{component_id: string, billing_basis: string, amount_minor: string, currency: string, unit_rate_minor: string, unit_label: string, minimum_minor: string, maximum_minor: string, is_prorated: bool, proration_basis: string, charge_frequency: string, is_optional: bool}>
     */
    public array $items = [];

    public ?int $matchCount = null;

    /**
     * @var array<int, array{id: int, admission_number: string, name: string}>
     */
    public array $matchSample = [];

    public function mount(School $school, ?FeeStructure $structure = null): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.fee_structure.manage');

        if ($structure === null) {
            $this->academicYearId = $school->currentAcademicYear()?->id;
            $this->addRule();
            $this->addItem();

            return;
        }

        $structure->load('rules', 'items');
        $this->revisingStructureId = $structure->id;
        $this->name = $structure->name;
        $this->priority = $structure->priority;
        $this->academicYearId = $structure->academic_year_id;
        $this->termId = $structure->term_id;

        $this->rules = $structure->rules->map(fn ($rule): array => [
            'attribute' => $rule->attribute,
            'operator' => $rule->operator,
            'value' => is_array($rule->value) ? implode(',', $rule->value) : (string) $rule->value,
        ])->values()->all();

        $this->items = $structure->items->map(fn ($item): array => [
            'component_id' => (string) $item->component_id,
            'billing_basis' => $item->billing_basis,
            'amount_minor' => $item->amount_minor !== null ? number_format($item->amount_minor / 100, 2, '.', '') : '',
            'currency' => $item->currency,
            'unit_rate_minor' => $item->unit_rate_minor !== null ? number_format($item->unit_rate_minor / 100, 2, '.', '') : '',
            'unit_label' => (string) $item->unit_label,
            'minimum_minor' => $item->minimum_minor !== null ? number_format($item->minimum_minor / 100, 2, '.', '') : '',
            'maximum_minor' => $item->maximum_minor !== null ? number_format($item->maximum_minor / 100, 2, '.', '') : '',
            'is_prorated' => $item->is_prorated,
            'proration_basis' => $item->proration_basis,
            'charge_frequency' => $item->charge_frequency,
            'is_optional' => $item->is_optional,
        ])->values()->all();

        if ($this->rules === []) {
            $this->addRule();
        }

        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function addRule(): void
    {
        $this->rules[] = ['attribute' => 'section', 'operator' => 'equals', 'value' => ''];
    }

    public function removeRule(int $index): void
    {
        unset($this->rules[$index]);
        $this->rules = array_values($this->rules);
    }

    public function addItem(): void
    {
        $this->items[] = [
            'component_id' => '', 'billing_basis' => 'flat_per_term', 'amount_minor' => '',
            'currency' => 'USD', 'unit_rate_minor' => '', 'unit_label' => '', 'minimum_minor' => '',
            'maximum_minor' => '', 'is_prorated' => true, 'proration_basis' => 'day',
            'charge_frequency' => 'termly', 'is_optional' => false,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function previewMatchCount(): void
    {
        if ($this->termId === null) {
            $this->addError('name', __('Pick a specific term to preview a match count against — a "whole year" structure has no single term to count active students in.'));

            return;
        }

        $result = app(CountLearnersMatchingRulesAction::class)->execute(new CountLearnersMatchingRulesData(
            schoolId: $this->school->id,
            termId: $this->termId,
            rules: $this->ruleData(),
        ));

        $this->matchCount = $result->count;
        $this->matchSample = $result->sample;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'priority' => ['required', 'integer', 'min:1'],
            'academicYearId' => ['required', 'integer'],
            'rules' => ['array', 'min:1'],
            'rules.*.attribute' => ['required', 'string'],
            'rules.*.operator' => ['required', 'string'],
            'items' => ['array', 'min:1'],
            'items.*.component_id' => ['required', 'integer'],
            'items.*.billing_basis' => ['required', 'string'],
            'items.*.currency' => ['required', 'size:3'],
        ]);

        try {
            if ($this->revisingStructureId !== null) {
                $structure = app(ReviseFeeStructureAction::class)->execute(new ReviseFeeStructureData(
                    structureId: $this->revisingStructureId,
                    rules: $this->ruleData(),
                    items: $this->itemData(),
                    revisedByUserId: (int) Auth::id(),
                ));
            } else {
                $structure = app(CreateFeeStructureAction::class)->execute(new CreateFeeStructureData(
                    schoolId: $this->school->id,
                    academicYearId: (int) $this->academicYearId,
                    name: $this->name,
                    priority: $this->priority,
                    rules: $this->ruleData(),
                    items: $this->itemData(),
                    createdByUserId: (int) Auth::id(),
                    termId: $this->termId,
                ));
            }
        } catch (DomainException $e) {
            $this->addError('name', $e->getMessage());

            return;
        }

        $this->redirectRoute('finance.fees.structures', ['school' => $this->school], navigate: true);
    }

    /**
     * @return array<int, array{attribute: string, operator: string, value: mixed}>
     */
    private function ruleData(): array
    {
        return array_map(fn (array $rule): array => [
            'attribute' => $rule['attribute'],
            'operator' => $rule['operator'],
            'value' => in_array($rule['operator'], ['in', 'not_in', 'between'], true)
                ? array_map('trim', explode(',', $rule['value']))
                : $rule['value'],
        ], $this->rules);
    }

    /**
     * @return array<int, array{component_id: int, billing_basis: string, currency: string, amount_minor: int|null, unit_rate_minor: int|null, unit_label: string|null, minimum_minor: int|null, maximum_minor: int|null, is_prorated: bool, proration_basis: string, charge_frequency: string, is_optional: bool}>
     */
    private function itemData(): array
    {
        return array_map(fn (array $item): array => [
            'component_id' => (int) $item['component_id'],
            'billing_basis' => $item['billing_basis'],
            'currency' => $item['currency'],
            'amount_minor' => $item['amount_minor'] !== '' ? (int) round(((float) $item['amount_minor']) * 100) : null,
            'unit_rate_minor' => $item['unit_rate_minor'] !== '' ? (int) round(((float) $item['unit_rate_minor']) * 100) : null,
            'unit_label' => $item['unit_label'] !== '' ? $item['unit_label'] : null,
            'minimum_minor' => $item['minimum_minor'] !== '' ? (int) round(((float) $item['minimum_minor']) * 100) : null,
            'maximum_minor' => $item['maximum_minor'] !== '' ? (int) round(((float) $item['maximum_minor']) * 100) : null,
            'is_prorated' => $item['is_prorated'],
            'proration_basis' => $item['proration_basis'],
            'charge_frequency' => $item['charge_frequency'],
            'is_optional' => $item['is_optional'],
        ], $this->items);
    }

    public function render(): View
    {
        return view('finance::fees.structure-builder', [
            'academicYears' => AcademicYear::where('school_id', $this->school->id)->orderByDesc('starts_on')->get(),
            'terms' => $this->academicYearId !== null
                ? Term::where('academic_year_id', $this->academicYearId)->orderBy('number')->get()
                : collect(),
            'components' => FeeComponent::where('is_active', true)->orderBy('code')->get(),
        ]);
    }
}

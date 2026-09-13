<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\PostingRules;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Domain\Actions\SetPostingRuleAction;
use Modules\Finance\Domain\DataObjects\SetPostingRuleData;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\CostCentre;
use Modules\Finance\Models\PostingRule;

/**
 * `Finance\PostingRules\Index` (Book B FIN-01 §8, `finance.posting_rule.manage`
 * to edit, `finance.posting_rule.view` to view) — which accounts a
 * financial event posts to. `event_key` has no fixed enum yet: nothing
 * in this pass of the codebase consults `posting_rules` (that's a later
 * FIN module's job, per this action's own docblock), so it stays a free
 * key a school defines ahead of the engine that will read it.
 */
#[Title('Posting rules')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public bool $showEditModal = false;

    public ?int $editingRuleId = null;

    public string $eventKey = '';

    public ?int $debitAccountId = null;

    public ?int $creditAccountId = null;

    public ?int $costCentreId = null;

    public bool $isActive = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.posting_rule.view');
    }

    public function openEditModal(?int $ruleId = null): void
    {
        $this->authorizePermission('finance.posting_rule.manage');
        $this->resetErrorBag();
        $this->editingRuleId = $ruleId;

        $rule = $ruleId !== null ? PostingRule::findOrFail($ruleId) : null;

        if ($rule === null) {
            $this->eventKey = '';
            $this->debitAccountId = null;
            $this->creditAccountId = null;
            $this->costCentreId = null;
            $this->isActive = true;
        } else {
            $this->eventKey = $rule->event_key;
            $this->debitAccountId = $rule->debit_account_id;
            $this->creditAccountId = $rule->credit_account_id;
            $this->costCentreId = $rule->cost_centre_id;
            $this->isActive = $rule->is_active;
        }

        $this->showEditModal = true;
    }

    public function save(): void
    {
        $this->authorizePermission('finance.posting_rule.manage');

        $this->validate([
            'eventKey' => ['required', 'string', 'max:100'],
            'debitAccountId' => ['nullable', 'integer'],
            'creditAccountId' => ['nullable', 'integer'],
            'costCentreId' => ['nullable', 'integer'],
        ]);

        try {
            app(SetPostingRuleAction::class)->execute(new SetPostingRuleData(
                schoolId: $this->school->id,
                eventKey: $this->eventKey,
                debitAccountId: $this->debitAccountId,
                creditAccountId: $this->creditAccountId,
                costCentreId: $this->costCentreId,
                isActive: $this->isActive,
            ));
        } catch (DomainException $e) {
            $this->addError('eventKey', $e->getMessage());

            return;
        }

        $this->showEditModal = false;
        $this->toast(__('Posting rule saved.'));
    }

    public function render(): View
    {
        $query = PostingRule::query()->with('debitAccount', 'creditAccount', 'costCentre')->orderBy('event_key');

        return view('finance::posting-rules.index', [
            'rules' => $this->paginateDataTable($query, $this->tableColumns()),
            'accounts' => Account::query()->where('is_postable', true)->orderBy('code')->get(),
            'costCentres' => CostCentre::query()->orderBy('code')->get(),
        ]);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'event_key' => ['label' => __('Event'), 'sortable' => true, 'searchable' => true],
            'is_active' => [
                'label' => __('Active'), 'sortable' => true, 'filter' => 'select',
                'options' => ['1' => __('Yes'), '0' => __('No')],
            ],
        ];
    }
}

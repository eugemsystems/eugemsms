<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Automation;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\AutomationRule;
use Modules\Comms\Models\RuleExecution;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Comms\Automation\ExecutionLog` (Book I COM-02 §5, `automation.view`).
 * Every attempt is logged (BR-COM-02-006), so the per-rule breakdown is
 * three counts of the same table: sent (matched with a notification),
 * throttled, and considered-but-not-matched. Subjects are shown as a
 * bare type and id — never the record's own data.
 */
#[Title('Automation execution log')]
#[Layout('layouts.app')]
final class ExecutionLog extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $ruleId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('automation.view');
    }

    public function render(): View
    {
        $base = RuleExecution::where('school_id', $this->school->id)
            ->when($this->ruleId !== null, fn ($query) => $query->where('rule_id', $this->ruleId));

        return view('comms::automation.execution-log', [
            'rules' => AutomationRule::where('school_id', $this->school->id)->orderBy('name')->get(['id', 'name']),
            'sent' => (clone $base)->where('matched', true)->whereNotNull('notification_id')->count(),
            'throttled' => (clone $base)->where('skip_reason', 'throttled')->count(),
            'notMatched' => (clone $base)->where('matched', false)->count(),
            'executions' => (clone $base)->orderByDesc('executed_at')->limit(100)->get(),
        ]);
    }
}

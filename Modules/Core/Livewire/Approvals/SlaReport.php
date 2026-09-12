<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Approvals;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\School;

/**
 * `Core\Approvals\SlaReport` (Book A CORE-07 §5, `core.approval.view_reports`)
 * — average time to approve, by type and by approver. "Time to
 * approve" is measured from `requested_at` to each terminal
 * (approved/rejected) `approval_actions` row's `acted_at`, computed in
 * PHP over completed requests for this school rather than a raw SQL
 * aggregate — the volumes an admin screen like this deals with don't
 * need one, and it keeps the by-type and by-approver breakdowns
 * sharing one pass over the same data.
 */
#[Title('Approvals SLA report')]
#[Layout('layouts.app')]
final class SlaReport extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.approval.view_reports');
    }

    public function render(): View
    {
        $requests = ApprovalRequest::query()
            ->where('school_id', $this->school->id)
            ->whereIn('status', ['approved', 'rejected'])
            ->with(['actions' => fn ($q) => $q->whereIn('action', ['approved', 'rejected'])->orderByDesc('acted_at')])
            ->get();

        $byType = [];
        $byApprover = [];

        foreach ($requests as $request) {
            $terminalAction = $request->actions->first();

            if ($terminalAction === null) {
                continue;
            }

            $hours = $request->requested_at->diffInHours($terminalAction->acted_at);

            $byType[$request->approvable_type][] = $hours;

            $approverId = $terminalAction->on_behalf_of_id ?? $terminalAction->actor_id;
            $byApprover[$approverId][] = $hours;
        }

        $approverNames = User::query()->whereIn('id', array_keys($byApprover))->pluck('name', 'id');

        return view('core::approvals.sla-report', [
            'byType' => $this->summarise($byType),
            'byApprover' => $this->summarise($byApprover, $approverNames),
        ]);
    }

    /**
     * @param  array<int|string, array<int, float>>  $groups
     * @param  Collection<int, string>|null  $labels
     * @return array<int, array{label: string, count: int, averageHours: float}>
     */
    private function summarise(array $groups, ?Collection $labels = null): array
    {
        $summary = [];

        foreach ($groups as $key => $hoursList) {
            $summary[] = [
                'label' => $labels !== null ? (string) ($labels[$key] ?? "#{$key}") : (string) $key,
                'count' => count($hoursList),
                'averageHours' => round(array_sum($hoursList) / count($hoursList), 1),
            ];
        }

        usort($summary, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $summary;
    }
}

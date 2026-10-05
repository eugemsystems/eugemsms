<?php

declare(strict_types=1);

namespace Modules\Intelligence\Livewire\EarlyWarning;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Intelligence\Domain\Actions\ComputeFeeDefaultRiskAction;
use Modules\Intelligence\Models\FeeDefaultRiskScore;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;

/**
 * `Intelligence\EarlyWarning\FeeRisk` (Book J INT-03 §5). The spec
 * names `finance.report.view`, which the Finance module never
 * registered; this screen accepts the two finance report permissions
 * that do exist for arrears (`finance.report.debtors`,
 * `finance.report.collections`). Recommends an action only
 * (BR-INT-03-006) — FIN-03's reminder ladder is untouched, and
 * nothing here contacts anyone.
 */
#[Title('Fee default risk')]
#[Layout('layouts.app')]
final class FeeRisk extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        abort_unless($this->mayView(), 403);
    }

    public function recompute(): void
    {
        $this->authorizePermission('risk.review');

        app(ComputeFeeDefaultRiskAction::class)->execute($this->school->id);

        $this->toast(__('Recomputed from outstanding invoices.'));
    }

    private function mayView(): bool
    {
        $user = auth()->user();
        $resolver = app(PermissionScopeResolver::class);

        return $user !== null && ($resolver->has($user, 'finance.report.debtors', PermissionScope::Own)
            || $resolver->has($user, 'finance.report.collections', PermissionScope::Own));
    }

    public function render(): View
    {
        $scores = FeeDefaultRiskScore::where('school_id', $this->school->id)->orderByDesc('risk_score')->limit(200)->get();
        $user = auth()->user();

        return view('intelligence::early-warning.fee-risk', [
            'scores' => $scores,
            'guardians' => Guardian::where('school_id', $this->school->id)->whereIn('id', $scores->pluck('guardian_id'))->get()->keyBy('id'),
            'students' => Student::where('school_id', $this->school->id)->whereIn('id', $scores->pluck('student_id'))->get()->keyBy('id'),
            'canRecompute' => $user !== null && app(PermissionScopeResolver::class)->has($user, 'risk.review', PermissionScope::Own),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Security\Livewire\Drills;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Security\Domain\Actions\RecordDrillFindingsAction;
use Modules\Security\Domain\DataObjects\RecordDrillFindingsData;
use Modules\Security\Models\EmergencyDrill;

/**
 * `Drills\Index` (Book H2 OPS-06 §5/BR-OPS-06-010,
 * `security.drill.manage`). The historical trend and review side of
 * a drill — triggering and running one live is `Muster\Index`'s own
 * job. Findings and actions-required use the gap-filling
 * `RecordDrillFindingsAction` this pass adds (see
 * `.ai/rules/security.md`).
 */
#[Title('Emergency drills')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $selectedDrillId = null;

    public ?string $findings = null;

    public ?string $actionsRequired = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('security.drill.manage');
    }

    public function select(int $drillId): void
    {
        $drill = EmergencyDrill::findOrFail($drillId);
        $this->selectedDrillId = $drillId;
        $this->findings = $drill->findings;
        $this->actionsRequired = $drill->actions_required;
    }

    public function save(): void
    {
        if ($this->selectedDrillId === null) {
            return;
        }

        app(RecordDrillFindingsAction::class)->execute($this->selectedDrillId, new RecordDrillFindingsData(
            findings: $this->findings,
            actionsRequired: $this->actionsRequired,
        ));

        $this->toast(__('Findings recorded.'));
    }

    public function render(): View
    {
        return view('security::drills.index', [
            'drills' => EmergencyDrill::where('school_id', $this->school->id)->orderByDesc('conducted_at')->limit(30)->get(),
        ]);
    }
}

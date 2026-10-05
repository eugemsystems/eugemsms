<?php

declare(strict_types=1);

namespace Modules\Security\Livewire\Muster;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Livewire\Sessions\Concerns\InteractsWithSession;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\People\Models\Student;
use Modules\Security\Domain\Actions\AssembleMusterRollAction;
use Modules\Security\Domain\Actions\CompleteMusterAction;
use Modules\Security\Domain\Actions\RecordMusterMarkAction;
use Modules\Security\Domain\Actions\TriggerEmergencyDrillAction;
use Modules\Security\Domain\DataObjects\MusterRosterEntry;
use Modules\Security\Domain\DataObjects\TriggerEmergencyDrillData;
use Modules\Security\Models\ContractorSiteVisit;
use Modules\Security\Models\MusterMark;

/**
 * `Muster\Index` (Book H2 OPS-06 §3 ⭐⭐/BR-OPS-06-008/009/010/011,
 * `security.muster`). The single most operationally important screen
 * in this module — the roster assembles live from five already-built
 * modules (`AssembleMusterRollAction`), sick bay and
 * evacuation-assistance learners always sort first, and completing
 * the muster opens a real `BRD-02` incident for every unaccounted
 * learner (`CompleteMusterAction`). The spec's own offline-cache and
 * 15-minute refresh requirement (BR-OPS-06-008's second half) is a
 * client/API concern no module in this codebase has a layer for yet —
 * documented, not silently dropped.
 */
#[Title('Muster roll')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use InteractsWithSession;
    use Toasts;

    public ?int $activeDrillId = null;

    public string $drillType = 'fire';

    public bool $isAnnounced = false;

    /** @var array<int, array{category: string, personType: string, personId: int, needsAssistance: bool, label: string, marked: bool}> */
    public array $roster = [];

    public ?int $mustered = null;

    public ?int $unaccounted = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->loadSessionContext($school);
        $this->authorizePermission('security.muster');
    }

    public function trigger(): void
    {
        $drill = app(TriggerEmergencyDrillAction::class)->execute(new TriggerEmergencyDrillData(
            schoolId: $this->school->id,
            termId: (int) SessionContext::termId(),
            drillType: $this->drillType,
            conductedByUserId: (int) auth()->id(),
            isAnnounced: $this->isAnnounced,
        ));

        $this->activeDrillId = $drill->id;
        $this->mustered = null;
        $this->unaccounted = null;
        $this->refreshRoster();
        $this->toast(__('Drill triggered — roster assembled.'));
    }

    public function refreshRoster(): void
    {
        if ($this->activeDrillId === null) {
            return;
        }

        $markedKeys = MusterMark::where('drill_id', $this->activeDrillId)
            ->get()
            ->map(fn (MusterMark $mark): string => "{$mark->person_type}:{$mark->person_id}")
            ->flip();

        $this->roster = app(AssembleMusterRollAction::class)->execute($this->school->id)
            ->map(fn (MusterRosterEntry $entry): array => [
                'category' => $entry->category,
                'personType' => $entry->personType,
                'personId' => $entry->personId,
                'needsAssistance' => $entry->needsAssistance,
                'label' => $this->labelFor($entry),
                'marked' => $markedKeys->has("{$entry->personType}:{$entry->personId}"),
            ])
            ->all();
    }

    public function mark(string $personType, int $personId): void
    {
        if ($this->activeDrillId === null) {
            return;
        }

        app(RecordMusterMarkAction::class)->execute(
            $this->school->id,
            $this->activeDrillId,
            $personType,
            $personId,
            (int) auth()->id(),
        );

        $this->refreshRoster();
    }

    public function complete(): void
    {
        if ($this->activeDrillId === null) {
            return;
        }

        $drill = app(CompleteMusterAction::class)->execute($this->activeDrillId, (int) SessionContext::termId());

        $this->mustered = $drill->mustered_headcount;
        $this->unaccounted = $drill->unaccounted_count;
        $this->refreshRoster();
        $this->toast(__('Muster completed.'), $this->unaccounted > 0 ? 'warning' : 'success');
    }

    private function labelFor(MusterRosterEntry $entry): string
    {
        return match ($entry->personType) {
            'student' => Student::find($entry->personId)?->fullName() ?? "Student #{$entry->personId}",
            'staff' => Staff::find($entry->personId)?->fullName() ?? "Staff #{$entry->personId}",
            'contractor_site_visit' => ContractorSiteVisit::find($entry->personId)?->contractorWorker->full_name ?? "Contractor #{$entry->personId}",
            default => ucfirst(str_replace('_', ' ', $entry->personType))." #{$entry->personId}",
        };
    }

    public function render(): View
    {
        return view('security::muster.index');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Breaches;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\RecordBreachNotificationDecisionAction;
use Modules\Compliance\Domain\Actions\RecordDataBreachAction;
use Modules\Compliance\Domain\DataObjects\RecordBreachNotificationDecisionData;
use Modules\Compliance\Domain\DataObjects\RecordDataBreachData;
use Modules\Compliance\Models\DataBreach;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Breaches` (Book H3 CMP-03 §4 ⚠⚠,
 * `privacy.breach.manage`). A breach `includesMinors` escalates
 * automatically — synchronously, to the head and the safeguarding
 * lead — inside `RecordDataBreachAction` itself (BR-CMP-03-011,
 * AC-CMP-03-004); this screen just surfaces `includes_minors` and
 * leaves the escalation to the backend. The notification decision and
 * its timing are recorded whether or not notification actually
 * happens (BR-CMP-03-012) — both booleans below default to false and
 * are saved either way.
 */
#[Title('Data breach register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $breachType = 'unauthorised_access';

    public string $description = '';

    public string $dataCategoriesText = '';

    public string $severity = 'low';

    public bool $includesMinors = false;

    public string $recordsAffected = '';

    public string $subjectsAffected = '';

    public ?int $decidingBreachId = null;

    public bool $authorityNotified = false;

    public bool $subjectsNotified = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.breach.manage');
    }

    public function record(): void
    {
        $this->authorizePermission('privacy.breach.manage');

        $this->validate([
            'breachType' => ['required', 'in:unauthorised_access,disclosure,loss,theft,system_compromise,misdirected_communication'],
            'description' => ['required', 'string'],
            'dataCategoriesText' => ['required', 'string'],
            'severity' => ['required', 'in:low,medium,high,critical'],
        ]);

        $categories = array_values(array_filter(array_map('trim', explode(',', $this->dataCategoriesText))));

        app(RecordDataBreachAction::class)->execute(new RecordDataBreachData(
            schoolId: $this->school->id,
            breachType: $this->breachType,
            description: $this->description,
            dataCategories: $categories,
            severity: $this->severity,
            includesMinors: $this->includesMinors,
            reportedByUserId: (int) auth()->id(),
            recordsAffected: $this->recordsAffected !== '' ? (int) $this->recordsAffected : null,
            subjectsAffected: $this->subjectsAffected !== '' ? (int) $this->subjectsAffected : null,
        ));

        $this->reset(['description', 'dataCategoriesText', 'recordsAffected', 'subjectsAffected', 'includesMinors']);
        $this->toast(__('Breach recorded.'));
    }

    public function startDecision(int $breachId): void
    {
        $this->decidingBreachId = $breachId;
        $this->authorityNotified = false;
        $this->subjectsNotified = false;
    }

    public function recordDecision(): void
    {
        $this->authorizePermission('privacy.breach.manage');

        app(RecordBreachNotificationDecisionAction::class)->execute(new RecordBreachNotificationDecisionData(
            breachId: (int) $this->decidingBreachId,
            authorityNotified: $this->authorityNotified,
            subjectsNotified: $this->subjectsNotified,
        ));

        $this->decidingBreachId = null;
        $this->toast(__('Notification decision recorded.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.breaches.index', [
            'breaches' => DataBreach::where('school_id', $this->school->id)->orderByDesc('detected_at')->get(),
        ]);
    }
}

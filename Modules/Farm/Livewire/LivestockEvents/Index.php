<?php

declare(strict_types=1);

namespace Modules\Farm\Livewire\LivestockEvents;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Farm\Domain\Actions\RecordLivestockEventAction;
use Modules\Farm\Domain\DataObjects\RecordLivestockEventData;
use Modules\Farm\Models\Livestock;
use Modules\Farm\Models\LivestockEvent;

/**
 * `LivestockEvents\Index` (Book H2 OPS-03 §5 ⭐/BR-OPS-03-012/014,
 * `farm.record`). `withdrawal_ends_on` is always computed from the
 * event date plus the withdrawal period here — `RecordLivestockEventAction`
 * itself derives it, this form only ever supplies the raw days.
 */
#[Title('Livestock events')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $livestockId = null;

    public string $eventType = 'treatment';

    public int $headCountAffected = 1;

    public ?string $description = null;

    public ?string $medication = null;

    public ?int $withdrawalPeriodDays = null;

    public ?float $weightKg = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('farm.record');
    }

    public function record(): void
    {
        $this->validate([
            'livestockId' => ['required', 'integer'],
            'eventType' => ['required', 'string'],
        ]);

        app(RecordLivestockEventAction::class)->execute(new RecordLivestockEventData(
            schoolId: $this->school->id,
            livestockId: (int) $this->livestockId,
            eventType: $this->eventType,
            eventDate: Carbon::now(),
            recordedByUserId: (int) auth()->id(),
            headCountAffected: $this->headCountAffected,
            description: $this->description,
            medication: $this->medication,
            withdrawalPeriodDays: $this->withdrawalPeriodDays,
            weightKg: $this->weightKg,
        ));

        $this->reset(['description', 'medication', 'withdrawalPeriodDays', 'weightKg']);
        $this->toast(__('Livestock event recorded.'));
    }

    public function render(): View
    {
        return view('farm::livestock-events.index', [
            'events' => LivestockEvent::with('livestock')->where('school_id', $this->school->id)->orderByDesc('event_date')->limit(100)->get(),
            'livestock' => Livestock::where('school_id', $this->school->id)->where('status', 'active')->get(),
        ]);
    }
}

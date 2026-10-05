<?php

declare(strict_types=1);

namespace Modules\Utilities\Livewire\LoadShedding;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Utilities\Domain\Actions\RecordLoadSheddingAction;
use Modules\Utilities\Domain\DataObjects\RecordLoadSheddingData;
use Modules\Utilities\Models\LoadSheddingSchedule;

/**
 * `LoadShedding\Index` (Book H2 OPS-04 §6 🇿🇼, `utilities.manage`).
 * Records either a published schedule entry or an observed actual
 * outage — the same form either way, per `RecordLoadSheddingAction`'s
 * own docblock.
 */
#[Title('Load shedding')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $scheduleDate = '';

    public ?string $stage = null;

    public string $startsAt = '';

    public string $endsAt = '';

    public string $source = 'published';

    public ?string $impactNote = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('utilities.manage');
        $this->scheduleDate = Carbon::now()->toDateString();
    }

    public function record(): void
    {
        $this->validate([
            'scheduleDate' => ['required', 'date'],
            'startsAt' => ['required'],
            'endsAt' => ['required'],
            'source' => ['required', 'string'],
        ]);

        app(RecordLoadSheddingAction::class)->execute(new RecordLoadSheddingData(
            schoolId: $this->school->id,
            scheduleDate: Carbon::parse($this->scheduleDate),
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            source: $this->source,
            stage: $this->stage !== '' ? $this->stage : null,
            impactNote: $this->impactNote !== '' ? $this->impactNote : null,
        ));

        $this->reset(['stage', 'impactNote']);
        $this->toast(__('Load shedding entry recorded.'));
    }

    public function render(): View
    {
        return view('utilities::load-shedding.index', [
            'entries' => LoadSheddingSchedule::where('school_id', $this->school->id)->orderByDesc('schedule_date')->limit(50)->get(),
        ]);
    }
}

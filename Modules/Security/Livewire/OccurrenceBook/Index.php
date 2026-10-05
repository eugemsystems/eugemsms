<?php

declare(strict_types=1);

namespace Modules\Security\Livewire\OccurrenceBook;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Security\Domain\Actions\RecordOccurrenceAction;
use Modules\Security\Domain\DataObjects\RecordOccurrenceData;
use Modules\Security\Models\OccurrenceBookEntry;

/**
 * `OccurrenceBook\Index` (Book H2 OPS-06 §5/BR-OPS-06-003/012,
 * `security.occurrence.record`). Append-only, gapless numbering —
 * this screen offers no edit or delete control for any row, because
 * the model itself refuses both and `RecordOccurrenceAction` has no
 * update path to call. A correction is always a new entry naming the
 * one it corrects.
 */
#[Title('Occurrence book')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $occurredAt = '';

    public string $category = 'observation';

    public string $description = '';

    public ?string $location = null;

    public ?string $personsInvolved = null;

    public ?string $actionTaken = null;

    public ?int $correctsEntryId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('security.occurrence.record');
        $this->occurredAt = Carbon::now()->format('Y-m-d\TH:i');
    }

    public function record(): void
    {
        $this->validate([
            'occurredAt' => ['required'],
            'category' => ['required', 'string'],
            'description' => ['required', 'string'],
        ]);

        app(RecordOccurrenceAction::class)->execute(new RecordOccurrenceData(
            schoolId: $this->school->id,
            occurredAt: Carbon::parse($this->occurredAt),
            category: $this->category,
            description: $this->description,
            recordedByUserId: (int) auth()->id(),
            location: $this->location,
            personsInvolved: $this->personsInvolved,
            actionTaken: $this->actionTaken,
            correctsEntryId: $this->correctsEntryId,
        ));

        $this->reset(['description', 'location', 'personsInvolved', 'actionTaken', 'correctsEntryId']);
        $this->toast(__('Occurrence recorded.'));
    }

    public function render(): View
    {
        return view('security::occurrence-book.index', [
            'entries' => OccurrenceBookEntry::where('school_id', $this->school->id)->orderByDesc('entry_number')->limit(50)->get(),
        ]);
    }
}

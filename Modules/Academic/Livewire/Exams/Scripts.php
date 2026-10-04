<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Exams;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CollectScriptBatchAction;
use Modules\Academic\Domain\Actions\HandoverScriptBatchAction;
use Modules\Academic\Domain\DataObjects\CollectScriptBatchData;
use Modules\Academic\Domain\DataObjects\HandoverScriptBatchData;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Academic\Models\ScriptBatch;
use Modules\Academic\Models\Venue;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;

/**
 * `Exams\Scripts` (Book E ACA-07 §3 ⭐/BR-ACA-07-012/AC-ACA-07-004,
 * `academic.exams.script_manage`). Collect opens the chain of custody;
 * handover writes an append-only custody entry before the batch's own
 * `current_holder_staff_id`/`status` change. A count mismatch sets
 * `discrepancy` immediately — this screen surfaces it as a danger badge,
 * never silently resolved.
 */
#[Title('Script tracking')]
#[Layout('layouts.app')]
final class Scripts extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $paperId = null;

    public ?int $collectVenueId = null;

    public string $scriptCount = '';

    public string $expectedCount = '';

    public ?int $collectedByStaffId = null;

    /** @var array<int, string> */
    public array $handoverCounts = [];

    /** @var array<int, int|null> */
    public array $handoverTo = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.exams.script_manage');
    }

    public function collect(): void
    {
        $this->validate([
            'paperId' => ['required', 'integer'],
            'collectVenueId' => ['required', 'integer'],
            'scriptCount' => ['required', 'integer', 'min:0'],
            'expectedCount' => ['required', 'integer', 'min:0'],
            'collectedByStaffId' => ['required', 'integer'],
        ]);

        app(CollectScriptBatchAction::class)->execute(new CollectScriptBatchData(
            paperId: $this->paperId,
            venueId: $this->collectVenueId,
            scriptCount: (int) $this->scriptCount,
            expectedCount: (int) $this->expectedCount,
            collectedByStaffId: $this->collectedByStaffId,
            recordedByUserId: (int) Auth::id(),
        ));

        $this->reset(['scriptCount', 'expectedCount', 'collectedByStaffId']);
        $this->toast(__('Script batch collected.'));
    }

    public function handover(int $batchId): void
    {
        $toStaffId = $this->handoverTo[$batchId] ?? null;
        $count = $this->handoverCounts[$batchId] ?? '';

        if ($toStaffId === null || $count === '') {
            $this->toast(__('A receiving staff member and a count are required.'), 'danger');

            return;
        }

        try {
            app(HandoverScriptBatchAction::class)->execute(new HandoverScriptBatchData(
                batchId: $batchId,
                toStaffId: $toStaffId,
                scriptCount: (int) $count,
                action: 'handed_over',
                recordedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        unset($this->handoverCounts[$batchId], $this->handoverTo[$batchId]);
        $this->toast(__('Handover recorded.'));
    }

    public function render(): View
    {
        return view('academic::exams.scripts', [
            'papers' => ExaminationPaper::where('school_id', $this->school->id)->with('subject')->get(),
            'venues' => Venue::where('school_id', $this->school->id)->orderBy('name')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('first_name')->get(),
            'batches' => $this->paperId !== null
                ? ScriptBatch::where('paper_id', $this->paperId)->with('currentHolder', 'custodyLog')->get()
                : collect(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Audit;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\School;

/**
 * `Core\Audit\RecordHistory` (Book A CORE-08 §5, `core.audit.view`) —
 * "embeddable timeline on any record's detail page" per the spec.
 * Genuinely embeddable: `@livewire('core::audit.record-history', [
 * 'subjectType' => ..., 'subjectId' => ...])` drops this into any
 * screen once that screen wants it — nothing currently does (no other
 * Book A screen has asked for it yet), so this also ships as its own
 * routed page taking the same two query-string values, so the screen
 * is independently reachable and testable rather than dead code
 * waiting for a caller.
 */
#[Title('Record history')]
#[Layout('layouts.app')]
final class RecordHistory extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $subjectType = '';

    public ?int $subjectId = null;

    public function mount(School $school, ?string $subjectType = null, ?int $subjectId = null): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.audit.view');

        $this->subjectType = $subjectType ?? '';
        $this->subjectId = $subjectId;
    }

    public function render(): View
    {
        $entries = collect();

        if ($this->subjectType !== '' && $this->subjectId !== null) {
            $entries = ActivityLogEntry::query()
                ->where('school_id', $this->school->id)
                ->where('subject_type', $this->subjectType)
                ->where('subject_id', $this->subjectId)
                ->with('causer')
                ->orderByDesc('created_at')
                ->get();
        }

        return view('core::audit.record-history', ['entries' => $entries]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Comms\Livewire\Calendar;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Domain\Actions\CreateCalendarEventAction;
use Modules\Comms\Domain\Actions\GetCalendarForViewerAction;
use Modules\Comms\Domain\Actions\RebuildCalendarAction;
use Modules\Comms\Livewire\Concerns\ResolvesAudienceScopes;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;

/**
 * `Comms\Calendar\View` (Book I COM-06 §4, `calendar.view`). An agenda
 * for one month, filtered to what the *viewing user* may see
 * (`GetCalendarForViewerAction`, BR-COM-06-003) — never the raw table.
 * Creating a manual event (an open day, a fun fair) and the on-demand
 * rebuild need `events.manage`; manual events stay outside the
 * nightly rebuild's reconciliation by design. The public iCal feed and
 * its token are API surfaces and are not built here.
 */
#[Title('Calendar')]
#[Layout('layouts.app')]
final class View extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use ResolvesAudienceScopes;
    use Toasts;

    public string $month = '';

    public string $title = '';

    public string $startsAt = '';

    public string $endsAt = '';

    public bool $isAllDay = false;

    public string $location = '';

    public string $description = '';

    public string $audienceScope = 'whole_school';

    public ?int $audienceScopeId = null;

    public bool $isPublic = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('calendar.view');

        $this->month = now()->format('Y-m');
    }

    public function createEvent(): void
    {
        $this->authorizePermission('events.manage');

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['nullable', 'date', 'after_or_equal:startsAt'],
            'location' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'audienceScope' => ['required', 'in:'.implode(',', array_keys($this->audienceScopeOptions()))],
        ]);

        $year = AcademicYear::where('school_id', $this->school->id)->where('is_current', true)->first();

        if ($year === null) {
            $this->addError('title', __('There is no current academic year to attach the event to.'));

            return;
        }

        app(CreateCalendarEventAction::class)->execute(
            schoolId: $this->school->id,
            academicYearId: $year->id,
            title: $this->title,
            startsAt: Carbon::parse($this->startsAt),
            audienceScope: $this->audienceScope,
            termId: $year->currentTerm()?->id,
            description: $this->description !== '' ? $this->description : null,
            endsAt: $this->endsAt !== '' ? Carbon::parse($this->endsAt) : null,
            isAllDay: $this->isAllDay,
            location: $this->location !== '' ? $this->location : null,
            audienceScopeId: $this->resolveAudienceScopeId($this->audienceScope, $this->audienceScopeId, $this->school->id),
            isPublic: $this->isPublic,
        );

        $this->reset(['title', 'startsAt', 'endsAt', 'isAllDay', 'location', 'description', 'audienceScopeId']);
        $this->toast(__('Event added to the calendar.'));
    }

    public function rebuild(): void
    {
        $this->authorizePermission('events.manage');

        $count = app(RebuildCalendarAction::class)->execute($this->school->id);

        $this->toast(__('Calendar rebuilt from its sources (:count event(s) refreshed).', ['count' => $count]));
    }

    public function render(): ViewContract
    {
        $start = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) === 1
            ? Carbon::createFromFormat('Y-m-d', $this->month.'-01')->startOfMonth()
            : now()->startOfMonth();

        $events = app(GetCalendarForViewerAction::class)->execute(auth()->user(), $this->school->id, $start, $start->copy()->endOfMonth());

        return view('comms::calendar.view', [
            'eventsByDay' => $events->groupBy(fn ($event): string => $event->starts_at->toDateString()),
            'scopeOptions' => $this->audienceScopeOptions(),
            'scopeTargets' => $this->audienceScopeTargets($this->audienceScope, $this->school->id),
            'canManage' => app(PermissionScopeResolver::class)->has(auth()->user(), 'events.manage', PermissionScope::Own),
        ]);
    }
}

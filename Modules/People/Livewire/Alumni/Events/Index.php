<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Alumni\Events;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Comms\Models\CalendarEvent;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\School;
use Modules\People\Domain\Actions\CreateAlumniEventAction;
use Modules\People\Domain\DataObjects\CreateAlumniEventData;
use Modules\People\Models\AlumniEvent;

/**
 * `Alumni\Events\Index` (Book K PPL-06 §5, `alumni.event.manage`). Built on
 * COM-06's calendar — the event itself is a calendar event; this adds only
 * the graduation-year targeting (BR-PPL-06-005).
 */
#[Title('Alumni events')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $title = '';

    public string $eventType = 'reunion';

    public string $startsAt = '';

    public string $location = '';

    public string $description = '';

    public string $targetYears = '';

    public bool $requiresTicket = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('alumni.event.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('alumni.event.manage');
        $this->resetErrorBag();

        $this->validate(['title' => ['required', 'string', 'max:200'], 'startsAt' => ['required', 'date'], 'location' => ['nullable', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:2000']]);

        $years = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $this->targetYears) ?: [])));

        if (array_filter($years, fn (string $year): bool => preg_match('/^\d{4}$/', $year) !== 1) !== []) {
            $this->addError('targetYears', __('List graduation years as four digits, e.g. 2015, 2016.'));

            return;
        }

        $academicYear = AcademicYear::query()->orderByDesc('is_current')->orderByDesc('starts_on')->first();

        if ($academicYear === null) {
            $this->addError('title', __('Set up an academic year first.'));

            return;
        }

        try {
            app(CreateAlumniEventAction::class)->execute(new CreateAlumniEventData(
                schoolId: $this->school->id, academicYearId: $academicYear->id, title: $this->title, startsAt: Carbon::parse($this->startsAt),
                eventType: $this->eventType, targetGraduationYears: $years === [] ? null : array_map('intval', $years), requiresTicket: $this->requiresTicket,
                description: $this->description === '' ? null : $this->description, location: $this->location === '' ? null : $this->location,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->addError('title', $exception->getMessage());

            return;
        }

        $this->reset('title', 'startsAt', 'location', 'description', 'targetYears', 'requiresTicket');
        $this->toast(__('Event created on the school calendar.'));
    }

    public function render(): View
    {
        $events = AlumniEvent::query()->orderByDesc('id')->limit(100)->get();

        return view('people::alumni.events', [
            'events' => $events,
            'calendar' => CalendarEvent::query()->whereIn('id', $events->pluck('calendar_event_id'))->get()->keyBy('id'),
        ]);
    }
}

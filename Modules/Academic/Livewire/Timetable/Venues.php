<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateVenueAction;
use Modules\Academic\Domain\DataObjects\CreateVenueData;
use Modules\Academic\Models\Venue;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Timetable\Venues` (Book E ACA-03 §2/§7, `academic.timetable.manage`
 * for both view and manage — the spec's own screen table lists only
 * `timetable.manage` for this screen, so this pass follows that exactly
 * rather than inventing a separate view permission). List + create —
 * `CreateVenueAction` has no `Update` counterpart.
 */
#[Title('Venues')]
#[Layout('layouts.app')]
final class Venues extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $venueType = 'classroom';

    public string $capacity = '';

    public string $examCapacity = '';

    public string $building = '';

    public string $floor = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.manage');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.timetable.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'venueType' => ['required', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'examCapacity' => ['nullable', 'integer', 'min:1'],
        ]);

        app(CreateVenueAction::class)->execute(new CreateVenueData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            venueType: $this->venueType,
            capacity: (int) $this->capacity,
            examCapacity: $this->examCapacity !== '' ? (int) $this->examCapacity : null,
            building: $this->building !== '' ? $this->building : null,
            floor: $this->floor !== '' ? $this->floor : null,
        ));

        $this->reset(['code', 'name', 'capacity', 'examCapacity', 'building', 'floor']);
        $this->toast(__('Venue created.'));
    }

    public function render(): View
    {
        return view('academic::timetable.venues', [
            'venues' => Venue::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

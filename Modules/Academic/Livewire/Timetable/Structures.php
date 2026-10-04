<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Timetable;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreatePeriodStructureAction;
use Modules\Academic\Domain\DataObjects\CreatePeriodStructureData;
use Modules\Academic\Domain\DataObjects\PeriodSlotInput;
use Modules\Academic\Models\PeriodStructure;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;

/**
 * `Timetable\Structures` (Book E ACA-03 §2/§7, `academic.timetable.manage`).
 * List + create — no `UpdatePeriodStructureAction` exists, matching this
 * pass's established create-only precedent. Takes the structure and its
 * complete slot set together in one form (a small JS-free repeater backed
 * by an indexed array), the same reason `CreatePeriodStructureAction`
 * itself takes them together.
 */
#[Title('Period structures')]
#[Layout('layouts.app')]
final class Structures extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $sectionId = null;

    public string $name = '';

    public string $cycleType = 'weekly';

    public int $cycleDays = 5;

    public bool $isDefault = false;

    /**
     * Named `slotRows`, not `slots` — a public `$slots` property on a
     * Livewire component collides with Livewire's own internal slot
     * mechanism (`Component::getSlots()` reads a `slots` bag it
     * expects to contain `Slot` objects, not this screen's own repeater
     * rows), which surfaces as an opaque "Call to a member function
     * getName() on array" error from `SupportSlots::dehydrate()` on
     * every render — not a validation error, a fatal one. Found running
     * this screen's own routed-request test.
     *
     * @var array<int, array{cycle_day: string, period_number: string, label: string, slot_type: string, starts_at: string, ends_at: string, duration_minutes: string, is_teachable: bool, requires_attendance: bool}>
     */
    public array $slotRows = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.timetable.view');

        $this->addSlot();
    }

    public function addSlot(): void
    {
        $this->slotRows[] = [
            'cycle_day' => '1', 'period_number' => (string) (count($this->slotRows) + 1),
            'label' => '', 'slot_type' => 'teaching', 'starts_at' => '', 'ends_at' => '',
            'duration_minutes' => '40', 'is_teachable' => true, 'requires_attendance' => true,
        ];
    }

    public function removeSlot(int $index): void
    {
        unset($this->slotRows[$index]);
        $this->slotRows = array_values($this->slotRows);
    }

    public function create(): void
    {
        $this->authorizePermission('academic.timetable.manage');

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'cycleType' => ['required', 'in:weekly,two_week,six_day,ten_day'],
            'cycleDays' => ['required', 'integer', 'min:1', 'max:14'],
            'slotRows' => ['required', 'array', 'min:1'],
            'slotRows.*.cycle_day' => ['required', 'integer'],
            'slotRows.*.period_number' => ['required', 'integer'],
            'slotRows.*.label' => ['required', 'string', 'max:40'],
            'slotRows.*.slot_type' => ['required', 'string'],
            'slotRows.*.starts_at' => ['required'],
            'slotRows.*.ends_at' => ['required'],
            'slotRows.*.duration_minutes' => ['required', 'integer', 'min:1'],
        ]);

        try {
            app(CreatePeriodStructureAction::class)->execute(new CreatePeriodStructureData(
                schoolId: $this->school->id,
                academicYearId: (int) $this->school->currentAcademicYear()?->id,
                name: $this->name,
                cycleType: $this->cycleType,
                cycleDays: $this->cycleDays,
                dayLabels: $this->defaultDayLabels(),
                slots: array_map(fn (array $s): PeriodSlotInput => new PeriodSlotInput(
                    cycleDay: (int) $s['cycle_day'],
                    periodNumber: (int) $s['period_number'],
                    label: $s['label'],
                    slotType: $s['slot_type'],
                    startsAt: $s['starts_at'],
                    endsAt: $s['ends_at'],
                    durationMinutes: (int) $s['duration_minutes'],
                    isTeachable: (bool) $s['is_teachable'],
                    requiresAttendance: (bool) $s['requires_attendance'],
                ), $this->slotRows),
                sectionId: $this->sectionId,
                isDefault: $this->isDefault,
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['name', 'sectionId', 'isDefault', 'slotRows']);
        $this->addSlot();
        $this->toast(__('Period structure created.'));
    }

    /**
     * @return array<int, string>
     */
    private function defaultDayLabels(): array
    {
        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        return $this->cycleType === 'weekly'
            ? array_slice($labels, 0, $this->cycleDays)
            : array_map(fn (int $i): string => "Day {$i}", range(1, $this->cycleDays));
    }

    public function render(): View
    {
        return view('academic::timetable.structures', [
            'structures' => PeriodStructure::where('school_id', $this->school->id)->with('slots')->orderByDesc('id')->get(),
            'sections' => SchoolSection::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

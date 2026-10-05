<?php

declare(strict_types=1);

namespace Modules\Operations\Livewire\Maintenance\Schedules;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Operations\Domain\Actions\CreateMaintenanceScheduleAction;
use Modules\Operations\Domain\Actions\GeneratePreventiveWorkOrdersAction;
use Modules\Operations\Domain\DataObjects\CreateMaintenanceScheduleData;
use Modules\Operations\Models\MaintenanceAsset;
use Modules\Operations\Models\MaintenanceSchedule;

/**
 * `Maintenance\Schedules\Index` (Book H2 OPS-02 §7/BR-OPS-02-009/010,
 * `maintenance.manage`). "Generate due" runs
 * `GeneratePreventiveWorkOrdersAction` on demand — this codebase has
 * no scheduled-command wiring for it yet (see
 * `OperationsServiceProvider`'s own docblock), so this button is the
 * honest on-demand stand-in, the same shape `Boarding\RollCall\Incidents`'s
 * "Check ladder" button already established for an un-cronned check.
 */
#[Title('Preventive maintenance schedules')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $maintenanceAssetId = null;

    public string $name = '';

    public string $triggerType = 'calendar';

    public string $assignedTeam = 'in_house';

    public ?int $intervalDays = null;

    public ?float $intervalUnits = null;

    public int $leadTimeDays = 7;

    public string $taskChecklistText = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('maintenance.manage');
    }

    public function create(): void
    {
        $this->validate([
            'maintenanceAssetId' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'triggerType' => ['required', 'in:calendar,usage,both'],
        ]);

        $checklist = array_values(array_filter(array_map('trim', explode("\n", $this->taskChecklistText))));

        app(CreateMaintenanceScheduleAction::class)->execute(new CreateMaintenanceScheduleData(
            schoolId: $this->school->id,
            maintenanceAssetId: (int) $this->maintenanceAssetId,
            name: $this->name,
            triggerType: $this->triggerType,
            assignedTeam: $this->assignedTeam,
            taskChecklist: $checklist,
            intervalDays: $this->intervalDays,
            intervalUnits: $this->intervalUnits,
            leadTimeDays: $this->leadTimeDays,
            nextDueOn: $this->triggerType !== 'usage' ? now()->addDays($this->intervalDays ?? 30) : null,
            nextDueUnits: $this->intervalUnits,
        ));

        $this->reset(['name', 'intervalDays', 'intervalUnits', 'taskChecklistText']);
        $this->toast(__('Schedule created.'));
    }

    public function generateDue(): void
    {
        $result = app(GeneratePreventiveWorkOrdersAction::class)->execute($this->school->id, (int) auth()->id());

        $this->toast(__(':count preventive work order(s) generated.', ['count' => $result->generated->count()]));
    }

    public function render(): View
    {
        return view('operations::maintenance.schedules.index', [
            'schedules' => MaintenanceSchedule::with('maintenanceAsset')->where('school_id', $this->school->id)->orderBy('name')->get(),
            'assets' => MaintenanceAsset::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

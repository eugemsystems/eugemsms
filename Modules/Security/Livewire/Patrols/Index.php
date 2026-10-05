<?php

declare(strict_types=1);

namespace Modules\Security\Livewire\Patrols;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Models\MovementCheckpoint;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Staff;
use Modules\Security\Domain\Actions\CheckMissedPatrolsAction;
use Modules\Security\Domain\Actions\CompletePatrolAction;
use Modules\Security\Domain\Actions\CreatePatrolRouteAction;
use Modules\Security\Domain\Actions\RecordPatrolScanAction;
use Modules\Security\Domain\Actions\SchedulePatrolAction;
use Modules\Security\Domain\DataObjects\CreatePatrolRouteData;
use Modules\Security\Models\Patrol;
use Modules\Security\Models\PatrolRoute;

/**
 * `Patrols\Index` (Book H2 OPS-06 §5/BR-OPS-06-004/005,
 * `security.patrol.manage`). Routes, schedule, checkpoint scans, and
 * a "Check missed patrols" button for `CheckMissedPatrolsAction`,
 * which this codebase has no cron wiring to call automatically, the
 * same uncronned-button precedent `Transport\FuelAnomalies\Index` and
 * `Utilities\Tokens\Index` already use.
 */
#[Title('Patrols')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $routeCode = '';

    public string $routeName = '';

    /** @var array<int, int> */
    public array $checkpointIds = [];

    public string $frequency = 'two_hourly';

    public ?int $patrolRouteId = null;

    public ?int $guardStaffId = null;

    public ?int $scanningPatrolId = null;

    public ?int $scanCheckpointId = null;

    public string $scanMethod = 'manual';

    public int $missedCount = 0;

    public bool $missedChecked = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('security.patrol.manage');
    }

    public function createRoute(): void
    {
        $this->validate([
            'routeCode' => ['required', 'string'],
            'routeName' => ['required', 'string'],
            'checkpointIds' => ['required', 'array', 'min:1'],
            'frequency' => ['required', 'string'],
        ]);

        app(CreatePatrolRouteAction::class)->execute(new CreatePatrolRouteData(
            schoolId: $this->school->id,
            code: $this->routeCode,
            name: $this->routeName,
            checkpointIds: array_map('intval', $this->checkpointIds),
            frequency: $this->frequency,
        ));

        $this->reset(['routeCode', 'routeName', 'checkpointIds']);
        $this->toast(__('Patrol route created.'));
    }

    public function schedule(): void
    {
        $this->validate([
            'patrolRouteId' => ['required', 'integer'],
            'guardStaffId' => ['required', 'integer'],
        ]);

        app(SchedulePatrolAction::class)->execute((int) $this->patrolRouteId, (int) $this->guardStaffId, Carbon::now());

        $this->toast(__('Patrol scheduled.'));
    }

    public function selectForScan(int $patrolId): void
    {
        $this->scanningPatrolId = $patrolId;
    }

    public function scan(): void
    {
        if ($this->scanningPatrolId === null || $this->scanCheckpointId === null) {
            return;
        }

        app(RecordPatrolScanAction::class)->execute($this->scanningPatrolId, $this->scanCheckpointId, $this->scanMethod);
        $this->toast(__('Checkpoint scanned.'));
    }

    public function complete(int $patrolId): void
    {
        app(CompletePatrolAction::class)->execute($patrolId);
        $this->toast(__('Patrol completed.'));
    }

    public function checkMissed(): void
    {
        $this->missedCount = app(CheckMissedPatrolsAction::class)->execute($this->school->id)->count();
        $this->missedChecked = true;
    }

    public function render(): View
    {
        return view('security::patrols.index', [
            'routes' => PatrolRoute::where('school_id', $this->school->id)->orderBy('code')->get(),
            'patrols' => Patrol::with('route', 'guardStaff')->where('school_id', $this->school->id)->orderByDesc('scheduled_at')->limit(30)->get(),
            'checkpoints' => MovementCheckpoint::where('school_id', $this->school->id)->orderBy('code')->get(),
            'staff' => Staff::where('school_id', $this->school->id)->orderBy('last_name')->get(),
        ]);
    }
}

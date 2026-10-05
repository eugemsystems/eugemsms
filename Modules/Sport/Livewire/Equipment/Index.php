<?php

declare(strict_types=1);

namespace Modules\Sport\Livewire\Equipment;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;
use Modules\Sport\Domain\Actions\CheckOverdueEquipmentAction;
use Modules\Sport\Domain\Actions\IssueEquipmentAction;
use Modules\Sport\Domain\Actions\ReturnEquipmentAction;
use Modules\Sport\Domain\DataObjects\IssueEquipmentData;
use Modules\Sport\Models\Activity;
use Modules\Sport\Models\EquipmentIssue;
use Modules\Stores\Models\FixedAsset;

/**
 * `Equipment\Index` (Book H2 OPS-07 §4, `activities.manage`). A
 * gap-filling screen — the spec's own screen table names no screen
 * for equipment issue, even though `IssueEquipmentAction`/
 * `ReturnEquipmentAction`/`CheckOverdueEquipmentAction` all exist
 * (see `.ai/rules/sport.md`). Tracked through `FIN-09`'s own
 * `FixedAsset` register per BR-OPS-07-011.
 */
#[Title('Equipment')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $activityId = null;

    public ?int $assetId = null;

    public ?int $studentId = null;

    public ?string $expectedReturnOn = null;

    public ?string $notes = null;

    public int $overdueCount = 0;

    public bool $overdueChecked = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('activities.manage');
    }

    public function issue(): void
    {
        $this->validate([
            'activityId' => ['required', 'integer'],
            'assetId' => ['required', 'integer'],
            'studentId' => ['required', 'integer'],
        ]);

        app(IssueEquipmentAction::class)->execute(new IssueEquipmentData(
            schoolId: $this->school->id,
            activityId: (int) $this->activityId,
            assetId: (int) $this->assetId,
            studentId: (int) $this->studentId,
            issuedByUserId: (int) auth()->id(),
            expectedReturnOn: $this->expectedReturnOn !== null && $this->expectedReturnOn !== '' ? Carbon::parse($this->expectedReturnOn) : null,
            notes: $this->notes,
        ));

        $this->reset(['assetId', 'studentId', 'expectedReturnOn', 'notes']);
        $this->toast(__('Equipment issued.'));
    }

    public function returnItem(int $issueId): void
    {
        app(ReturnEquipmentAction::class)->execute($issueId);
        $this->toast(__('Equipment returned.'));
    }

    public function checkOverdue(): void
    {
        $this->overdueCount = app(CheckOverdueEquipmentAction::class)->execute($this->school->id)->count();
        $this->overdueChecked = true;
    }

    public function render(): View
    {
        return view('sport::equipment.index', [
            'issues' => EquipmentIssue::with('activity', 'student', 'asset')->where('school_id', $this->school->id)->orderByDesc('issued_at')->limit(50)->get(),
            'activities' => Activity::where('school_id', $this->school->id)->orderBy('name')->get(),
            'assets' => FixedAsset::where('school_id', $this->school->id)->orderBy('name')->limit(200)->get(),
            'students' => Student::where('school_id', $this->school->id)->orderBy('last_name')->limit(300)->get(),
        ]);
    }
}

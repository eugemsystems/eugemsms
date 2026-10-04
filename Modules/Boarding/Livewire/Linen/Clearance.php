<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Linen;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CheckLinenClearanceAction;
use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\People\Models\Student;

/**
 * `Linen\Clearance` (Book F BRD-05 §4, `linen.manage`). Per-learner
 * clearance check — `CheckLinenClearanceAction` is a standalone,
 * callable query, deliberately NOT wired into
 * `Modules\People\Domain\Actions\WithdrawStudentAction` in this pass
 * (see that action's own docblock); this screen is where a registrar
 * runs the check by hand before signing off a transfer or withdrawal.
 */
#[Title('Linen clearance')]
#[Layout('layouts.app')]
final class Clearance extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $studentId = null;

    public bool $checked = false;

    public bool $isClear = false;

    /** @var array<int, int> */
    public array $outstandingItemIds = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.linen.manage');
    }

    public function check(): void
    {
        if ($this->studentId === null) {
            return;
        }

        $result = app(CheckLinenClearanceAction::class)->execute($this->school->id, $this->studentId);
        $this->checked = true;
        $this->isClear = $result->isClear;
        $this->outstandingItemIds = $result->outstandingItemIds;
    }

    public function render(): View
    {
        $outstanding = $this->outstandingItemIds !== []
            ? LearnerIssuedItem::whereIn('id', $this->outstandingItemIds)->with('issuableItem')->get()
            : collect();

        return view('boarding::linen.clearance', [
            'students' => Student::where('school_id', $this->school->id)->orderBy('first_name')->limit(300)->get(),
            'outstanding' => $outstanding,
        ]);
    }
}

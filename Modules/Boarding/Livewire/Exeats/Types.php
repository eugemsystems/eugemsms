<?php

declare(strict_types=1);

namespace Modules\Boarding\Livewire\Exeats;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Boarding\Domain\Actions\CreateExeatTypeAction;
use Modules\Boarding\Domain\DataObjects\CreateExeatTypeData;
use Modules\Boarding\Models\ExeatQuota;
use Modules\Boarding\Models\ExeatType;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Exeats\Types` (Book F BRD-03 §6, `boarding.exeat.manage`). List +
 * create, plus a read-only roll-up of per-learner quota usage for
 * each type — folding the spec's separate "Exeat quotas" screen in
 * here, since a quota only ever exists against a type and no Action
 * pre-sets one ahead of a request (`ExeatQuota` rows are created
 * on-demand inside `RequestExeatAction` itself via `firstOrCreate`).
 */
#[Title('Exeat types & quotas')]
#[Layout('layouts.app')]
final class Types extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public ?int $maxDurationHours = null;

    public bool $requiresGuardianRequest = true;

    public int $minNoticeHours = 24;

    public ?int $allowedPerTerm = null;

    public bool $blocksOnSuspension = true;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('boarding.exeat.manage');
    }

    public function create(): void
    {
        $this->validate(['code' => ['required', 'string', 'max:30'], 'name' => ['required', 'string', 'max:120']]);

        app(CreateExeatTypeAction::class)->execute(new CreateExeatTypeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            maxDurationHours: $this->maxDurationHours,
            requiresGuardianRequest: $this->requiresGuardianRequest,
            minNoticeHours: $this->minNoticeHours,
            allowedPerTerm: $this->allowedPerTerm,
            blocksOnSuspension: $this->blocksOnSuspension,
        ));

        $this->reset(['code', 'name', 'maxDurationHours', 'allowedPerTerm']);
        $this->toast(__('Exeat type created.'));
    }

    public function render(): View
    {
        $types = ExeatType::where('school_id', $this->school->id)->get();

        return view('boarding::exeats.types', [
            'types' => $types,
            'quotas' => ExeatQuota::where('school_id', $this->school->id)->with('student', 'exeatType')->orderByDesc('id')->limit(50)->get(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateSubjectGroupAction;
use Modules\Academic\Domain\DataObjects\CreateSubjectGroupData;
use Modules\Academic\Models\SubjectGroup;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeStructureItem;

/**
 * `Curriculum\Groups` (Book D ACA-01 §5 ⭐/BR-ACA-01-004,
 * `academic.curriculum.manage` to create, `.view` to list). Shows the
 * `FIN-02` fee rate mapped to each group's own `code` inline
 * (`fee_structure_items.subject_rate_map`) — the spec's own warning
 * that a bursar renaming a group without realising it is the key
 * `FIN-02` prices against is exactly the error this screen exists to
 * prevent.
 */
#[Title('Subject groups')]
#[Layout('layouts.app')]
final class Groups extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public bool $requiresLaboratory = false;

    public bool $requiresWorkshop = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.curriculum.view');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.curriculum.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
        ]);

        app(CreateSubjectGroupAction::class)->execute(new CreateSubjectGroupData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            description: $this->description !== '' ? $this->description : null,
            requiresLaboratory: $this->requiresLaboratory,
            requiresWorkshop: $this->requiresWorkshop,
        ));

        $this->reset(['code', 'name', 'description', 'requiresLaboratory', 'requiresWorkshop']);
        $this->toast(__('Subject group created.'));
    }

    public function render(): View
    {
        $groups = SubjectGroup::where('school_id', $this->school->id)->orderBy('name')->get();

        /** @var array<string, int> $rateByGroupCode */
        $rateByGroupCode = [];

        foreach (FeeStructureItem::where('school_id', $this->school->id)->where('billing_basis', 'per_subject')->get() as $item) {
            foreach ((array) $item->subject_rate_map as $groupCode => $rateMinor) {
                $rateByGroupCode[$groupCode] = (int) $rateMinor;
            }
        }

        return view('academic::curriculum.groups', [
            'groups' => $groups,
            'rateByGroupCode' => $rateByGroupCode,
        ]);
    }
}

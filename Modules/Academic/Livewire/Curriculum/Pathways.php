<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Curriculum;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreatePathwayAction;
use Modules\Academic\Domain\DataObjects\CreatePathwayData;
use Modules\Academic\Models\CurriculumFramework;
use Modules\Academic\Models\Pathway;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Curriculum\Pathways` (Book D ACA-01 §5 🇿🇼/BR-ACA-01-013,
 * `academic.curriculum.manage` to create, `.view` to list).
 */
#[Title('Pathways')]
#[Layout('layouts.app')]
final class Pathways extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $frameworkId = null;

    public string $code = '';

    public string $name = '';

    public string $description = '';

    public string $appliesFromLevelOrdinal = '8';

    public bool $isDefault = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.curriculum.view');

        $this->frameworkId = CurriculumFramework::where('school_id', $school->id)->orderByDesc('effective_from')->value('id');
    }

    public function create(): void
    {
        $this->authorizePermission('academic.curriculum.manage');

        $this->validate([
            'frameworkId' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'appliesFromLevelOrdinal' => ['required', 'integer', 'min:1'],
        ]);

        app(CreatePathwayAction::class)->execute(new CreatePathwayData(
            schoolId: $this->school->id,
            frameworkId: (int) $this->frameworkId,
            code: $this->code,
            name: $this->name,
            appliesFromLevelOrdinal: (int) $this->appliesFromLevelOrdinal,
            description: $this->description !== '' ? $this->description : null,
            isDefault: $this->isDefault,
        ));

        $this->reset(['code', 'name', 'description', 'isDefault']);
        $this->toast(__('Pathway created.'));
    }

    public function render(): View
    {
        return view('academic::curriculum.pathways', [
            'pathways' => Pathway::where('school_id', $this->school->id)->with('framework')->orderBy('name')->get(),
            'frameworks' => CurriculumFramework::where('school_id', $this->school->id)->orderByDesc('effective_from')->get(),
        ]);
    }
}

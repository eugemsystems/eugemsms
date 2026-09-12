<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Templates;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;

/**
 * `Core\Templates\Versions` (Book A CORE-06 §6, `core.template.view`)
 * — every version of one template type, newest first. "Diff between
 * versions" (the spec's own description) is a side-by-side raw content
 * comparison of two selected versions rather than a computed line
 * diff — this project has no diff library in its production
 * dependencies (`sebastian/diff` is a transitive dev-only dependency
 * of PHPUnit, not safe to rely on outside tests) and adding one is a
 * dependency change outside this task's scope.
 */
#[Title('Template version history')]
#[Layout('layouts.app')]
final class Versions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $templateType;

    public ?int $compareLeftId = null;

    public ?int $compareRightId = null;

    public function mount(School $school, string $templateType): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.template.view');

        $this->templateType = $templateType;
    }

    public function render(): View
    {
        $versions = DocumentTemplate::query()
            ->where('school_id', $this->school->id)
            ->where('template_type', $this->templateType)
            ->orderByDesc('version')
            ->get();

        return view('core::templates.versions', [
            'versions' => $versions,
            'compareLeft' => $versions->firstWhere('id', $this->compareLeftId),
            'compareRight' => $versions->firstWhere('id', $this->compareRightId),
        ]);
    }
}

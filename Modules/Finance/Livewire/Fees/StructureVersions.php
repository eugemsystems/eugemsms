<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Fees;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Finance\Models\FeeStructure;

/**
 * `Finance\Fees\StructureVersions` (Book B FIN-02 §7, `finance.fee_structure.view`)
 * — every version of one structure "family" (same school/year/term/name,
 * BR-FIN-02-011's own versioning key), and a rules/items diff between
 * any two of them.
 */
#[Title('Structure versions')]
#[Layout('layouts.app')]
final class StructureVersions extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public FeeStructure $structure;

    public ?int $compareLeftId = null;

    public ?int $compareRightId = null;

    public function mount(School $school, FeeStructure $structure): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.fee_structure.view');
        $this->structure = $structure;

        $versions = $this->versions();
        $previous = $versions->where('version', '<', $structure->version)->last();
        $this->compareRightId = $structure->id;
        $this->compareLeftId = $previous === null ? $structure->id : $previous->id;
    }

    /**
     * @return Collection<int, FeeStructure>
     */
    private function versions(): Collection
    {
        return FeeStructure::query()
            ->where('school_id', $this->school->id)
            ->where('academic_year_id', $this->structure->academic_year_id)
            ->where('term_id', $this->structure->term_id)
            ->where('name', $this->structure->name)
            ->with('rules', 'items.component')
            ->orderBy('version')
            ->get();
    }

    public function render(): View
    {
        $versions = $this->versions();

        return view('finance::fees.structure-versions', [
            'versions' => $versions,
            'left' => $versions->firstWhere('id', $this->compareLeftId),
            'right' => $versions->firstWhere('id', $this->compareRightId),
        ]);
    }
}

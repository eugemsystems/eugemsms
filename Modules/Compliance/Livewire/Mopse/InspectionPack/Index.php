<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Mopse\InspectionPack;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\GenerateInspectionPackAction;
use Modules\Compliance\Domain\DataObjects\GenerateInspectionPackData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\File;
use Modules\Core\Models\School;

/**
 * `Compliance\Mopse\InspectionPack` (Book H3 CMP-02 §3 ⭐, `mopse.manage`).
 * On-demand assembly of attendance registers, staff records,
 * establishment posts and statutory documents into one JSON document
 * (BR-CMP-02-005, AC-CMP-02-003) — given its own screen rather than
 * folded into `SchoolReturns` because it isn't a `statutory_school_returns`
 * row at all, just a `File`. `policy_acknowledgements` is explicitly
 * null in the generated pack until CMP-04 ships (the action's own
 * documented forward dependency).
 */
#[Title('Inspection readiness pack')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $periodStart = '';

    public string $periodEnd = '';

    public ?int $generatedFileId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('mopse.manage');

        $this->periodStart = now()->subMonth()->toDateString();
        $this->periodEnd = now()->toDateString();
    }

    public function generate(): void
    {
        $this->authorizePermission('mopse.manage');

        $this->validate([
            'periodStart' => ['required', 'date'],
            'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
        ]);

        $file = app(GenerateInspectionPackAction::class)->execute(new GenerateInspectionPackData(
            schoolId: $this->school->id,
            periodStart: Carbon::parse($this->periodStart),
            periodEnd: Carbon::parse($this->periodEnd),
            generatedByUserId: (int) auth()->id(),
        ));

        $this->generatedFileId = $file->id;
        $this->toast(__('Inspection pack generated: :name', ['name' => $file->original_name]));
    }

    public function render(): View
    {
        return view('compliance::mopse.inspection-pack.index', [
            'generatedFile' => $this->generatedFileId !== null ? File::find($this->generatedFileId) : null,
            'recentPacks' => File::where('school_id', $this->school->id)->where('category', 'inspection_pack')->orderByDesc('id')->limit(10)->get(),
        ]);
    }
}

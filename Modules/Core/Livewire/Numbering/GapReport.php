<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Numbering;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Documents\GenerateGapReportAction;
use Modules\Core\Domain\DataObjects\Documents\GapReportData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\NumberingSeries;
use Modules\Core\Models\School;

/**
 * `Core\Numbering\GapReport` (Book A CORE-06 §6/BR-CORE-06-006,
 * `core.numbering.view`) — every voided sequence with its reason,
 * optionally narrowed to one series.
 */
#[Title('Numbering gap report')]
#[Layout('layouts.app')]
final class GapReport extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public ?int $seriesId = null;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.numbering.view');
    }

    public function render(): View
    {
        $entries = app(GenerateGapReportAction::class)->execute(new GapReportData(
            schoolId: $this->school->id,
            seriesId: $this->seriesId,
        ));

        return view('core::numbering.gap-report', [
            'entries' => $entries,
            'series' => NumberingSeries::query()->where('school_id', $this->school->id)->orderBy('document_type')->get(),
        ]);
    }
}

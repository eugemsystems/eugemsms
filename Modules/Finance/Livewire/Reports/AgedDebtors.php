<?php

declare(strict_types=1);

namespace Modules\Finance\Livewire\Reports;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolSection;
use Modules\Finance\Domain\Actions\GenerateAgedDebtorsReportAction;
use Modules\Finance\Domain\DataObjects\GenerateAgedDebtorsReportData;

/**
 * `Finance\Reports\AgedDebtors` (Book B FIN-03 §5/BR-FIN-03-014,
 * `finance.report.debtors`) — by class, section, residency, currency;
 * drill to learner.
 */
#[Title('Aged debtors')]
#[Layout('layouts.app')]
final class AgedDebtors extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public string $currency = 'USD';

    public ?int $sectionId = null;

    public ?int $gradeLevelId = null;

    public string $residency = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('finance.report.debtors');
    }

    public function render(): View
    {
        $rows = app(GenerateAgedDebtorsReportAction::class)->execute(new GenerateAgedDebtorsReportData(
            schoolId: $this->school->id,
            currency: $this->currency,
            asAt: now(),
            sectionId: $this->sectionId,
            gradeLevelId: $this->gradeLevelId,
            residency: $this->residency !== '' ? $this->residency : null,
        ));

        return view('finance::reports.aged-debtors', [
            'rows' => $rows,
            'sections' => SchoolSection::orderBy('name')->get(),
            'gradeLevels' => GradeLevel::orderBy('ordinal')->get(),
        ]);
    }
}

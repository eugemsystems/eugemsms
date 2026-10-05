<?php

declare(strict_types=1);

namespace Modules\Payroll\Livewire\Grades;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;
use Modules\Payroll\Domain\Actions\CreatePayGradeAction;
use Modules\Payroll\Domain\Actions\CreatePayGradeNotchAction;
use Modules\Payroll\Domain\DataObjects\CreatePayGradeData;
use Modules\Payroll\Domain\DataObjects\CreatePayGradeNotchData;
use Modules\Payroll\Models\PayGrade;

/**
 * `Payroll\Grades\Index` (Book H3 PPL-05 §2/§6, `payroll.manage`).
 * List + create — no `UpdatePayGradeAction` exists, the same
 * create-only precedent every prior book's admin-UI pass has
 * established. Also folds in notch creation
 * (`CreatePayGradeNotchAction`, a new, narrow, create-only Action
 * this pass added — `pay_grade_notches` had a migration/model/
 * factory but no Action anywhere ever created a row) since the spec
 * names no separate "notches" screen.
 */
#[Title('Pay grades')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $category = 'teaching';

    public string $currency = 'USD';

    public string $minSalaryMinor = '';

    public string $maxSalaryMinor = '';

    public string $necGradeReference = '';

    public ?int $notchGradeId = null;

    public string $notchLabel = '';

    public string $notchBasicSalaryMinor = '';

    public string $notchCurrency = 'USD';

    public string $notchEffectiveFrom = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('payroll.view');

        $this->notchEffectiveFrom = now()->toDateString();
    }

    public function create(): void
    {
        $this->authorizePermission('payroll.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:teaching,administration,support,management,ancillary'],
            'currency' => ['required', 'in:USD,ZWG'],
        ]);

        app(CreatePayGradeAction::class)->execute(new CreatePayGradeData(
            schoolId: $this->school->id,
            code: $this->code,
            name: $this->name,
            category: $this->category,
            currency: $this->currency,
            minSalaryMinor: $this->minSalaryMinor !== '' ? (int) $this->minSalaryMinor : null,
            maxSalaryMinor: $this->maxSalaryMinor !== '' ? (int) $this->maxSalaryMinor : null,
            necGradeReference: $this->necGradeReference !== '' ? $this->necGradeReference : null,
        ));

        $this->reset(['code', 'name', 'minSalaryMinor', 'maxSalaryMinor', 'necGradeReference']);
        $this->toast(__('Pay grade created.'));
    }

    public function addNotch(): void
    {
        $this->authorizePermission('payroll.manage');

        $this->validate([
            'notchGradeId' => ['required', 'integer'],
            'notchLabel' => ['required', 'string', 'max:20'],
            'notchBasicSalaryMinor' => ['required', 'integer', 'gt:0'],
            'notchCurrency' => ['required', 'in:USD,ZWG'],
            'notchEffectiveFrom' => ['required', 'date'],
        ]);

        app(CreatePayGradeNotchAction::class)->execute(new CreatePayGradeNotchData(
            schoolId: $this->school->id,
            gradeId: (int) $this->notchGradeId,
            notch: $this->notchLabel,
            basicSalaryMinor: (int) $this->notchBasicSalaryMinor,
            currency: $this->notchCurrency,
            effectiveFrom: Carbon::parse($this->notchEffectiveFrom),
        ));

        $this->reset(['notchLabel', 'notchBasicSalaryMinor']);
        $this->toast(__('Notch added.'));
    }

    public function render(): View
    {
        return view('payroll::grades.index', [
            'grades' => PayGrade::where('school_id', $this->school->id)->with('notches')->orderBy('code')->get(),
        ]);
    }
}

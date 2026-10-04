<?php

declare(strict_types=1);

namespace Modules\Academic\Livewire\Grading;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Academic\Domain\Actions\CreateGradingScaleAction;
use Modules\Academic\Domain\DataObjects\CreateGradingScaleData;
use Modules\Academic\Domain\DataObjects\GradeBandInput;
use Modules\Academic\Domain\Exceptions\GradeBandGapOrOverlapException;
use Modules\Academic\Models\GradingScale;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Grading\Scales` (Book D ACA-05 §6/BR-ACA-05-001/002,
 * `academic.grading.manage` to create, `.view` to list). The band
 * editor enforces nothing client-side beyond a sensible default
 * (three bands spanning 0–100) — the real contiguity check is
 * `CreateGradingScaleAction`'s own, surfaced here as a plain error
 * naming the gap or overlap (AC-ACA-05-008).
 */
#[Title('Grading scales')]
#[Layout('layouts.app')]
final class Scales extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $code = '';

    public string $name = '';

    public string $scaleType = 'letter';

    public bool $lowerIsBetter = false;

    public string $passGrade = '';

    /** @var array<int, array{grade: string, minPercent: string, maxPercent: string, points: string, isPass: bool}> */
    public array $bands = [];

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('academic.grading.view');

        $this->bands = [
            ['grade' => 'A', 'minPercent' => '75', 'maxPercent' => '100', 'points' => '', 'isPass' => true],
            ['grade' => 'B', 'minPercent' => '50', 'maxPercent' => '75', 'points' => '', 'isPass' => true],
            ['grade' => 'U', 'minPercent' => '0', 'maxPercent' => '50', 'points' => '', 'isPass' => false],
        ];
    }

    public function addBand(): void
    {
        $this->bands[] = ['grade' => '', 'minPercent' => '', 'maxPercent' => '', 'points' => '', 'isPass' => true];
    }

    public function removeBand(int $index): void
    {
        unset($this->bands[$index]);
        $this->bands = array_values($this->bands);
    }

    public function create(): void
    {
        $this->authorizePermission('academic.grading.manage');

        $this->validate([
            'code' => ['required', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'scaleType' => ['required', 'in:letter,unit,band,points,percentage'],
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.grade' => ['required', 'string', 'max:10'],
            'bands.*.minPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'bands.*.maxPercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $bandInputs = array_map(fn (array $band): GradeBandInput => new GradeBandInput(
            grade: $band['grade'],
            minPercent: (float) $band['minPercent'],
            maxPercent: (float) $band['maxPercent'],
            isPass: $band['isPass'],
            points: $band['points'] !== '' ? (float) $band['points'] : null,
        ), $this->bands);

        try {
            app(CreateGradingScaleAction::class)->execute(new CreateGradingScaleData(
                schoolId: $this->school->id,
                code: $this->code,
                name: $this->name,
                scaleType: $this->scaleType,
                bands: $bandInputs,
                lowerIsBetter: $this->lowerIsBetter,
                passGrade: $this->passGrade !== '' ? $this->passGrade : null,
            ));
        } catch (GradeBandGapOrOverlapException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->reset(['code', 'name', 'passGrade']);
        $this->toast(__('Grading scale created.'));
    }

    public function render(): View
    {
        return view('academic::grading.scales', [
            'scales' => GradingScale::where('school_id', $this->school->id)->with('bands')->orderBy('name')->get(),
        ]);
    }
}

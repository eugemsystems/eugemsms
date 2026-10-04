<?php

declare(strict_types=1);

namespace Modules\People\Livewire\Students;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\GradeLevel;
use Modules\Core\Models\School;
use Modules\Core\Models\SchoolClass;
use Modules\Core\Models\SchoolSection;
use Modules\People\Domain\Actions\ChangeBillingAttributeAction;
use Modules\People\Domain\Actions\PreviewBillingAttributeChangeAction;
use Modules\People\Domain\DataObjects\ChangeBillingAttributeData;
use Modules\People\Models\Student;

/**
 * `People\Students\ChangeAttribute` (Book C PPL-01 §8 ⭐/BR-PPL-01-004,
 * `students.change_billing_attribute`). "The registrar should see
 * 'this will credit USD 369.23...' before they click confirm" — see
 * `PreviewBillingAttributeChangeAction`'s own docblock for how that
 * preview is computed without persisting anything.
 */
#[Title('Change billing attribute')]
#[Layout('layouts.app')]
final class ChangeAttribute extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;

    public Student $student;

    public string $attribute = 'enrolment_type';

    public string $newValue = '';

    public string $effectiveFrom = '';

    public string $reasonCode = '';

    public string $reason = '';

    /**
     * A plain array, not the `BillingAttributeChangePreview` object
     * itself — Livewire has no synth for an arbitrary DTO class on a
     * public property.
     *
     * @var array{currentAmountMinor: int|null, currentCurrency: string|null, proposedAmountMinor: int|null, proposedCurrency: string|null, deltaMinor: int|null, hasComparableFigures: bool}|null
     */
    public ?array $preview = null;

    public function mount(School $school, Student $student): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('people.students.change_billing_attribute');

        abort_unless($student->school_id === $school->id, 404);

        $this->student = $student;
        $this->effectiveFrom = now()->toDateString();
    }

    public function updatedAttribute(): void
    {
        $this->newValue = '';
        $this->preview = null;
    }

    public function updatedNewValue(): void
    {
        $this->preview = null;
    }

    public function previewImpact(): void
    {
        $this->validate(['newValue' => ['required', 'string']]);

        $termId = $this->student->enrolments()->latest('id')->value('term_id');

        if ($termId === null) {
            $this->addError('newValue', __('This learner has no enrolment to preview a fee impact against.'));

            return;
        }

        $result = app(PreviewBillingAttributeChangeAction::class)->execute(
            $this->student->id, $termId, $this->attribute, $this->newValue,
        );

        $this->preview = [
            'currentAmountMinor' => $result->currentAmountMinor,
            'currentCurrency' => $result->currentCurrency,
            'proposedAmountMinor' => $result->proposedAmountMinor,
            'proposedCurrency' => $result->proposedCurrency,
            'deltaMinor' => $result->deltaMinor(),
            'hasComparableFigures' => $result->hasComparableFigures(),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'newValue' => ['required', 'string'],
            'effectiveFrom' => ['required', 'date'],
        ]);

        try {
            app(ChangeBillingAttributeAction::class)->execute(new ChangeBillingAttributeData(
                studentId: $this->student->id,
                attribute: $this->attribute,
                newValue: $this->newValue,
                effectiveFrom: Carbon::parse($this->effectiveFrom),
                changedByUserId: (int) Auth::id(),
                reasonCode: $this->reasonCode !== '' ? $this->reasonCode : null,
                reason: $this->reason !== '' ? $this->reason : null,
            ));
        } catch (DomainException $e) {
            $this->addError('newValue', $e->getMessage());

            return;
        }

        $this->redirectRoute('people.students.show', ['school' => $this->school, 'student' => $this->student], navigate: true);
    }

    public function render(): View
    {
        return view('people::students.change-attribute', [
            'sections' => SchoolSection::where('school_id', $this->school->id)->orderBy('name')->get(),
            'gradeLevels' => GradeLevel::where('school_id', $this->school->id)->orderBy('ordinal')->get(),
            'classes' => SchoolClass::where('school_id', $this->school->id)->orderBy('name')->get(),
        ]);
    }
}

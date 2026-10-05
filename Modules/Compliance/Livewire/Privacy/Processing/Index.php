<?php

declare(strict_types=1);

namespace Modules\Compliance\Livewire\Privacy\Processing;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Compliance\Domain\Actions\RegisterProcessingActivityAction;
use Modules\Compliance\Domain\DataObjects\RegisterProcessingActivityData;
use Modules\Compliance\Models\ProcessingRegisterEntry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\School;

/**
 * `Compliance\Privacy\Processing` (Book H3 CMP-03 §4, spec names
 * `privacy.view` for reading this register; registering a new entry
 * is a write this pass gates behind `privacy.manage` instead, the
 * same split `Consents` uses).
 */
#[Title('Processing register')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $activityName = '';

    public string $purpose = '';

    public string $lawfulBasis = 'consent';

    public string $dataCategoriesText = '';

    public string $subjectCategoriesText = '';

    public string $owningModule = '';

    public bool $involvesMinors = false;

    public bool $isSpecialCategory = false;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('privacy.view');
    }

    public function register(): void
    {
        $this->authorizePermission('privacy.manage');

        $this->validate([
            'activityName' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'string'],
            'lawfulBasis' => ['required', 'in:consent,contract,legal_obligation,vital_interest,legitimate_interest'],
            'dataCategoriesText' => ['required', 'string'],
            'subjectCategoriesText' => ['required', 'string'],
            'owningModule' => ['required', 'string', 'max:20'],
        ]);

        app(RegisterProcessingActivityAction::class)->execute(new RegisterProcessingActivityData(
            schoolId: $this->school->id,
            activityName: $this->activityName,
            purpose: $this->purpose,
            lawfulBasis: $this->lawfulBasis,
            dataCategories: array_values(array_filter(array_map('trim', explode(',', $this->dataCategoriesText)))),
            subjectCategories: array_values(array_filter(array_map('trim', explode(',', $this->subjectCategoriesText)))),
            owningModule: $this->owningModule,
            involvesMinors: $this->involvesMinors,
            isSpecialCategory: $this->isSpecialCategory,
        ));

        $this->reset(['activityName', 'purpose', 'dataCategoriesText', 'subjectCategoriesText', 'owningModule', 'involvesMinors', 'isSpecialCategory']);
        $this->toast(__('Processing activity registered.'));
    }

    public function render(): View
    {
        return view('compliance::privacy.processing.index', [
            'entries' => ProcessingRegisterEntry::where('school_id', $this->school->id)->orderBy('activity_name')->get(),
        ]);
    }
}

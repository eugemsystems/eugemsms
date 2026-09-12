<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Numbering;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Documents\CreateNumberingSeriesAction;
use Modules\Core\Domain\Actions\Documents\UpdateNumberingSeriesAction;
use Modules\Core\Domain\DataObjects\Documents\CreateNumberingSeriesData;
use Modules\Core\Domain\DataObjects\Documents\UpdateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\NumberingSeries;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * `Core\Numbering\Editor` (Book A CORE-06 §6, `core.numbering.manage`)
 * — one component for both create and edit, the same "optional model
 * parameter" shape `Users\Form` uses (`numbering/create` vs
 * `numbering/{series}/edit`).
 *
 * A series's `document_type`/`academic_year_id`/`term_id` are fixed at
 * creation — `UpdateNumberingSeriesData` has no fields for them, since
 * changing what a series numbers or which period it covers after
 * numbers may already have been allocated against it would break the
 * gapless guarantee. Edit mode shows them read-only for context and
 * only lets pattern/prefix/padding/reset-policy/active change.
 */
#[Title('Numbering series')]
#[Layout('layouts.app')]
final class Editor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $editingSeriesId = null;

    public string $documentType = '';

    public ?int $academicYearId = null;

    public ?int $termId = null;

    public string $pattern = '';

    public string $prefix = '';

    public int $sequencePadding = 6;

    public string $resetPolicy = 'never';

    public bool $isActive = true;

    public function mount(School $school, ?NumberingSeries $series = null): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.numbering.manage');

        if ($series === null) {
            return;
        }

        abort_unless($series->school_id === $school->id, 403);

        $this->editingSeriesId = $series->id;
        $this->documentType = $series->document_type;
        $this->academicYearId = $series->academic_year_id;
        $this->termId = $series->term_id;
        $this->pattern = $series->pattern;
        $this->prefix = (string) $series->prefix;
        $this->sequencePadding = $series->sequence_padding;
        $this->resetPolicy = $series->reset_policy;
        $this->isActive = $series->is_active;
    }

    public function save(): void
    {
        $this->validate([
            'documentType' => $this->editingSeriesId === null ? ['required', 'string', 'max:40'] : [],
            'pattern' => ['required', 'string', 'max:120'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'sequencePadding' => ['required', 'integer', 'min:1', 'max:10'],
            'resetPolicy' => ['required', Rule::in(['never', 'yearly', 'termly'])],
        ]);

        try {
            if ($this->editingSeriesId !== null) {
                app(UpdateNumberingSeriesAction::class)->execute(new UpdateNumberingSeriesData(
                    seriesId: $this->editingSeriesId,
                    pattern: $this->pattern,
                    prefix: $this->prefix !== '' ? $this->prefix : null,
                    sequencePadding: $this->sequencePadding,
                    resetPolicy: $this->resetPolicy,
                    isActive: $this->isActive,
                ));
            } else {
                app(CreateNumberingSeriesAction::class)->execute(new CreateNumberingSeriesData(
                    schoolId: $this->school->id,
                    documentType: $this->documentType,
                    pattern: $this->pattern,
                    academicYearId: $this->academicYearId,
                    termId: $this->termId,
                    prefix: $this->prefix !== '' ? $this->prefix : null,
                    sequencePadding: $this->sequencePadding,
                    resetPolicy: $this->resetPolicy,
                ));
            }
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast($this->editingSeriesId !== null ? __('Numbering series updated.') : __('Numbering series created.'));

        $this->redirectRoute('numbering.index', ['school' => $this->school], navigate: true);
    }

    public function render(): View
    {
        return view('core::numbering.editor', [
            'academicYears' => AcademicYear::query()->where('school_id', $this->school->id)->orderByDesc('starts_on')->get(),
            'terms' => Term::query()->where('school_id', $this->school->id)->with('academicYear')->orderByDesc('starts_on')->get(),
        ]);
    }
}

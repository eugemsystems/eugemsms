<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Templates;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Documents\CreateDocumentTemplateAction;
use Modules\Core\Domain\Actions\Documents\UpdateDocumentTemplateAction;
use Modules\Core\Domain\DataObjects\Documents\CreateDocumentTemplateData;
use Modules\Core\Domain\DataObjects\Documents\UpdateDocumentTemplateData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Registry\TemplateVariableRegistry;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;

/**
 * `Core\Templates\Editor` (Book A CORE-06 §6, `core.template.create`/
 * `core.template.update`) — one component for both create and a new
 * version, the same "optional model parameter" shape `Users\Form` uses
 * (`templates/create` vs `templates/{template}/edit`).
 *
 * "Split pane: code, variable palette, live preview against sample
 * data" (the spec's own screen description) is simplified here to
 * content editor + variable palette, no live-rendered preview: a
 * generic preview would need realistic sample data for an arbitrary
 * template type, which is exactly the data shape CORE-06 deliberately
 * doesn't own (each type's owning module does) — see this class's own
 * note on `TemplateVariableRegistry` below.
 *
 * `TemplateVariableRegistry` currently has ZERO registered types: no
 * other module has shipped its own registration yet (the spec frames
 * this as their responsibility, not CORE-06's — "a report card's
 * layout belongs to ACA-05, which registers a template type here").
 * Saving a template that references any variable therefore genuinely
 * fails validation until some module registers that type — this is
 * the correct, spec-described state, not a bug; a template with no
 * variable references (static content) saves and renders fine today.
 */
#[Title('Document template')]
#[Layout('layouts.app')]
final class Editor extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ?int $editingTemplateId = null;

    public string $templateType = '';

    public string $name = '';

    public string $content = '';

    public string $styles = '';

    public string $headerContent = '';

    public string $footerContent = '';

    public string $pageSize = 'A4';

    public string $orientation = 'portrait';

    public bool $isDefault = true;

    public function mount(School $school, ?DocumentTemplate $template = null): void
    {
        $this->loadSchool($school);
        $this->authorizePermission($template === null ? 'core.template.create' : 'core.template.update');

        if ($template === null) {
            return;
        }

        abort_unless($template->school_id === $school->id, 403);

        $this->editingTemplateId = $template->id;
        $this->templateType = $template->template_type;
        $this->name = $template->name;
        $this->content = $template->content;
        $this->styles = (string) $template->styles;
        $this->headerContent = (string) $template->header_content;
        $this->footerContent = (string) $template->footer_content;
        $this->pageSize = $template->page_size;
        $this->orientation = $template->orientation;
        $this->isDefault = $template->is_default;
    }

    public function save(): void
    {
        $this->validate([
            'templateType' => $this->editingTemplateId === null ? ['required', 'string', 'max:40'] : [],
            'name' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string'],
            'styles' => ['nullable', 'string'],
            'headerContent' => ['nullable', 'string'],
            'footerContent' => ['nullable', 'string'],
            'pageSize' => ['required', Rule::in(['A4', 'A5', 'Letter'])],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
        ]);

        try {
            if ($this->editingTemplateId !== null) {
                $template = app(UpdateDocumentTemplateAction::class)->execute(new UpdateDocumentTemplateData(
                    templateId: $this->editingTemplateId,
                    content: $this->content,
                    styles: $this->styles !== '' ? $this->styles : null,
                    name: $this->name,
                    headerContent: $this->headerContent !== '' ? $this->headerContent : null,
                    footerContent: $this->footerContent !== '' ? $this->footerContent : null,
                    updatedByUserId: (int) Auth::id(),
                ));
            } else {
                $template = app(CreateDocumentTemplateAction::class)->execute(new CreateDocumentTemplateData(
                    schoolId: $this->school->id,
                    templateType: $this->templateType,
                    name: $this->name,
                    content: $this->content,
                    styles: $this->styles !== '' ? $this->styles : null,
                    pageSize: $this->pageSize,
                    orientation: $this->orientation,
                    headerContent: $this->headerContent !== '' ? $this->headerContent : null,
                    footerContent: $this->footerContent !== '' ? $this->footerContent : null,
                    isDefault: $this->isDefault,
                    createdByUserId: (int) Auth::id(),
                ));
            }
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->toast($this->editingTemplateId !== null ? __('New template version created.') : __('Template created.'));

        $this->redirectRoute('templates.index', ['school' => $this->school], navigate: true);
    }

    public function render(): View
    {
        return view('core::templates.editor', [
            'availableVariables' => $this->templateType !== '' ? TemplateVariableRegistry::for($this->templateType) : [],
        ]);
    }
}

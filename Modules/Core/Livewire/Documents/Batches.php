<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Documents;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Documents\InitiateDocumentBatchAction;
use Modules\Core\Domain\DataObjects\Documents\InitiateDocumentBatchData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\DocumentBatch;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\School;

/**
 * `Core\Documents\Batches` (Book A CORE-06 §6/§10, `core.document.generate`)
 * — batch monitor. `InitiateDocumentBatchAction` only creates the batch
 * header (`status = queued`); the fan-out job that actually generates
 * each document (`JOB-GenerateDocumentBatch`) is, per that Action's own
 * docblock, deferred to a later wave along with every other CORE
 * background job in Book A — a batch created here correctly stays
 * `queued` until that job exists, it is not silently lost.
 */
#[Title('Document batches')]
#[Layout('layouts.app')]
final class Batches extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public bool $showCreateModal = false;

    public string $documentType = '';

    public ?int $templateId = null;

    public int $totalCount = 1;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.document.generate');
    }

    public function openCreateModal(): void
    {
        $this->reset(['documentType', 'templateId', 'totalCount']);
        $this->totalCount = 1;
        $this->showCreateModal = true;
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->validate([
            'documentType' => ['required', 'string', 'max:40'],
            'templateId' => ['required', 'integer'],
            'totalCount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            app(InitiateDocumentBatchAction::class)->execute(new InitiateDocumentBatchData(
                schoolId: $this->school->id,
                documentType: $this->documentType,
                templateId: $this->templateId,
                totalCount: $this->totalCount,
                requestedByUserId: (int) Auth::id(),
            ));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');

            return;
        }

        $this->showCreateModal = false;
        $this->toast(__('Batch queued.'));
    }

    public function render(): View
    {
        return view('core::documents.batches', [
            'batches' => DocumentBatch::query()->where('school_id', $this->school->id)->orderByDesc('id')->paginate(15),
            'templates' => DocumentTemplate::query()->where('school_id', $this->school->id)->where('is_active', true)->orderBy('template_type')->get(),
        ]);
    }
}

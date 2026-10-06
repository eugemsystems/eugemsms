<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Documents;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Documents\RecordDocumentDownloadAction;
use Modules\Core\Domain\DataObjects\Documents\RecordDocumentDownloadData;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\Document;
use Modules\Core\Models\School;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Core\Documents\Index` (Book A CORE-06 §6, `core.document.view`) —
 * the generated document archive. `download()` (`core.document.download`)
 * records the download (BR-CORE-06-015) then streams the file straight
 * back through this authenticated, permission-gated screen — the
 * public, unauthenticated signed-URL endpoint the spec describes
 * (`GET /api/v1/documents/{ulid}/download`) is API-layer work for a
 * later wave (see `Modules\Core\Domain\Support\Files\
 * SignedFileUrlGenerator`'s own docblock for the identical situation
 * on the file vault side); an admin who already passed this screen's
 * own permission check has no need for a second, time-limited signature
 * on top of it.
 */
#[Title('Document archive')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public string $search = '';

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.document.view');
    }

    /**
     * `Storage::disk(...)->download()` returns a `BinaryFileResponse` on
     * a real local disk but a `StreamedResponse` on the fake disk used
     * in tests — both extend `Response`, which is why this is typed
     * against the common base rather than the more specific class.
     */
    public function download(int $documentId): ?Response
    {
        $this->authorizePermission('core.document.download');

        $document = Document::where('school_id', $this->school->id)->findOrFail($documentId);

        if (! Storage::disk(config('filesystems.documents_disk'))->exists($document->file_path)) {
            $this->toast(__('This document\'s file is missing from storage.'), 'danger');

            return null;
        }

        app(RecordDocumentDownloadAction::class)->execute(new RecordDocumentDownloadData($document->id));

        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        $filename = trim("{$document->document_type}-{$document->number}", '-').".{$extension}";

        return Storage::disk(config('filesystems.documents_disk'))->download($document->file_path, $filename);
    }

    public function render(): View
    {
        $documents = Document::query()
            ->where('school_id', $this->school->id)
            ->when($this->search !== '', fn ($query) => $query->where(function ($q): void {
                $q->where('document_type', 'like', "%{$this->search}%")
                    ->orWhere('number', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('generated_at')
            ->paginate(15);

        return view('core::documents.index', ['documents' => $documents]);
    }
}

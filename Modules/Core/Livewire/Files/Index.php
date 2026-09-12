<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Files;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Files\DeleteFileAction;
use Modules\Core\Domain\Actions\Files\GenerateSignedFileUrlAction;
use Modules\Core\Domain\DataObjects\Files\GenerateSignedFileUrlData;
use Modules\Core\Domain\Exceptions\FileAccessDeniedException;
use Modules\Core\Domain\Support\Auth\PermissionScope;
use Modules\Core\Domain\Support\Auth\PermissionScopeResolver;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Concerns\InteractsWithDataTable;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\File;
use Modules\Core\Models\FileCategory;
use Modules\Core\Models\School;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Core\Files\Index` (Book A CORE-10 §6, `core.file.view`) — the file
 * vault browser: every upload across every attachable, regardless of
 * which module or record it's attached to.
 *
 * `download()` reuses `GenerateSignedFileUrlAction` purely for its side
 * effects (the scan-status gate and the sensitive-category access-log
 * write, BR-CORE-10-005/007) then streams the bytes back through this
 * authenticated, permission-gated screen directly, discarding the
 * generated URL — the public, unauthenticated `/files/{ulid}/download`
 * endpoint `SignedFileUrlGenerator` builds URLs for is API-layer work
 * for a later wave (see that class's own docblock), the same situation
 * `Documents\Index::download()` already resolved identically for
 * generated documents.
 *
 * A sensitive-category file additionally requires `core.file.view_sensitive`
 * — BR-CORE-10-007's "explicit permission beyond simple record access" —
 * on top of the baseline `core.file.view` needed just to browse the vault
 * and see that the file exists.
 */
#[Title('File vault')]
#[Layout('layouts.app')]
final class Index extends Component
{
    use AuthorizesPermissions;
    use InteractsWithDataTable;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.file.view');
    }

    public function canViewSensitive(): bool
    {
        return $this->hasPermission('core.file.view_sensitive');
    }

    public function canDelete(): bool
    {
        return $this->hasPermission('core.file.delete');
    }

    /**
     * `Storage::disk(...)->download()` returns a `BinaryFileResponse` on
     * a real local disk but a `StreamedResponse` on the fake disk used
     * in tests — both extend `Response`, same reasoning as
     * `Documents\Index::download()`.
     */
    public function download(int $fileId): ?Response
    {
        $file = File::where('school_id', $this->school->id)->findOrFail($fileId);

        if ($file->is_sensitive) {
            $this->authorizePermission('core.file.view_sensitive');
        }

        try {
            app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData(
                fileId: $file->id,
                requestedByUserId: (int) Auth::id(),
                action: 'download',
                ip: request()->ip(),
            ));
        } catch (FileAccessDeniedException $e) {
            $this->toast($e->getMessage(), 'danger');

            return null;
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            $this->toast(__('This file is missing from storage.'), 'danger');

            return null;
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function delete(int $fileId): void
    {
        $this->authorizePermission('core.file.delete');

        $file = File::where('school_id', $this->school->id)->findOrFail($fileId);

        app(DeleteFileAction::class)->execute($file);

        $this->toast(__('File deleted.'));
    }

    public function render(): View
    {
        $query = File::query()->where('school_id', $this->school->id)->with('uploadedBy');

        return view('core::files.index', [
            'files' => $this->paginateDataTable($query, $this->tableColumns()),
            'categoryOptions' => FileCategory::query()->orderBy('label')->pluck('label', 'key')->all(),
        ]);
    }

    private function hasPermission(string $name): bool
    {
        $user = Auth::user();

        return $user !== null && app(PermissionScopeResolver::class)->has($user, $name, PermissionScope::Own, $this->school->id);
    }

    /**
     * @return array<string, array{label: string, column?: string, sortable?: bool, searchable?: bool, filter?: string|null, options?: array<int|string, string>}>
     */
    protected function tableColumns(): array
    {
        return [
            'original_name' => ['label' => __('File'), 'sortable' => true, 'searchable' => true],
            'category' => [
                'label' => __('Category'), 'sortable' => true, 'searchable' => true,
                'filter' => 'select', 'options' => FileCategory::query()->orderBy('label')->pluck('label', 'key')->all(),
            ],
            'scan_status' => [
                'label' => __('Scan'), 'sortable' => true, 'filter' => 'select',
                'options' => ['pending' => __('Pending'), 'clean' => __('Clean'), 'infected' => __('Infected'), 'skipped' => __('Skipped')],
            ],
            'size_bytes' => ['label' => __('Size'), 'sortable' => true],
            'uploaded_by' => ['label' => __('Uploaded by')],
            'created_at' => ['label' => __('Uploaded'), 'sortable' => true],
        ];
    }
}

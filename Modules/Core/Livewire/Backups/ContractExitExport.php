<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Backups;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Backups\GenerateContractExitExportAction;
use Modules\Core\Domain\Actions\Files\GenerateSignedFileUrlAction;
use Modules\Core\Domain\DataObjects\Backups\GenerateContractExitExportData;
use Modules\Core\Domain\DataObjects\Files\GenerateSignedFileUrlData;
use Modules\Core\Domain\Exceptions\FileAccessDeniedException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\File;
use Modules\Core\Models\School;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Core\Backups\ContractExitExport` (Book A CORE-13 §5/BR-CORE-13-008,
 * `core.backup.export`) — on-demand generation of one school's complete
 * data in open formats, plus every export generated so far for this
 * school. Unlike `Index`/`Show`, this genuinely has one active school
 * (`{school}` route param), so the ordinary `InteractsWithSchool` +
 * `authorizePermission()` enforcement applies normally here.
 *
 * `download()` mirrors `Files\Index::download()` exactly — the export
 * is stored through the File Vault under its own `contract_exit_export`
 * category, so it gets the same scan-status gate and sensitive-category
 * access log any other vault file gets, for free.
 */
#[Title('Contract-exit export')]
#[Layout('layouts.app')]
final class ContractExitExport extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public function mount(School $school): void
    {
        $this->loadSchool($school);
        $this->authorizePermission('core.backup.export');
    }

    public function generate(): void
    {
        app(GenerateContractExitExportAction::class)->execute(new GenerateContractExitExportData(
            schoolId: $this->school->id,
            requestedByUserId: (int) Auth::id(),
        ));

        $this->toast(__('Export generated — download it below.'));
    }

    public function download(int $fileId): ?Response
    {
        $file = File::where('school_id', $this->school->id)->where('category', 'contract_exit_export')->findOrFail($fileId);

        try {
            app(GenerateSignedFileUrlAction::class)->execute(new GenerateSignedFileUrlData(
                fileId: $file->id,
                requestedByUserId: (int) Auth::id(),
                action: 'download',
            ));
        } catch (FileAccessDeniedException $e) {
            $this->toast($e->getMessage(), 'danger');

            return null;
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function render(): View
    {
        return view('core::backups.contract-exit-export', [
            'exports' => File::where('school_id', $this->school->id)
                ->where('category', 'contract_exit_export')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }
}

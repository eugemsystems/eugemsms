<?php

declare(strict_types=1);

namespace Modules\Core\Livewire\Imports;

use App\Concerns\Toasts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Core\Domain\Actions\Files\GenerateSignedFileUrlAction;
use Modules\Core\Domain\Actions\Imports\ExecuteImportBatchAction;
use Modules\Core\Domain\Actions\Imports\RollbackImportBatchAction;
use Modules\Core\Domain\Actions\Imports\ValidateImportBatchAction;
use Modules\Core\Domain\DataObjects\Files\GenerateSignedFileUrlData;
use Modules\Core\Domain\DataObjects\Imports\ExecuteImportBatchData;
use Modules\Core\Domain\DataObjects\Imports\RollbackImportBatchData;
use Modules\Core\Domain\DataObjects\Imports\ValidateImportBatchData;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Exceptions\FileAccessDeniedException;
use Modules\Core\Livewire\Concerns\AuthorizesPermissions;
use Modules\Core\Livewire\Schools\Concerns\InteractsWithSchool;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\ImportDefinition;
use Modules\Core\Models\School;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Core\Import\Batch` (Book A CORE-11 §5) — one screen covering the
 * whole batch lifecycle: `Core\Import\Validation`'s report,
 * `Core\Import\Progress`'s live counters, and a completed/rolled-back
 * batch's own detail view from `Core\Import\History`. Folded into one
 * component because every one of those is just a different `status`
 * of the same `ImportBatch` row, not separate state — three routes to
 * the same handful of fields would only fragment it.
 *
 * "Live counters" (§5's `Progress`) means something different here than
 * the spec's own framing suggests: `ExecuteImportBatchAction` processes
 * every valid row inline within one request/transaction rather than as
 * a queued, chunked job (BR-CORE-11-012 isn't built yet — no
 * `ImportBatchJob` exists to report incremental progress from), so
 * `approveAndImport()` blocks until the whole batch finishes and this
 * screen then shows the final counts, not a live-updating progress bar.
 * A 3,000-row import timing out (the exact case BR-CORE-11-012 names)
 * is a real risk this synchronous path doesn't yet solve — flagged here
 * rather than silently claimed as done.
 */
#[Title('Import batch')]
#[Layout('layouts.app')]
final class Batch extends Component
{
    use AuthorizesPermissions;
    use InteractsWithSchool;
    use Toasts;

    public ImportBatch $batch;

    public string $requiredPermission;

    public function mount(School $school, ImportBatch $batch): void
    {
        $this->loadSchool($school);
        abort_unless($batch->school_id === $school->id, 404);

        $definition = ImportDefinition::where('key', $batch->definition_key)->first();
        abort_if($definition === null, 404);

        $this->authorizePermission($definition->required_permission);
        $this->requiredPermission = $definition->required_permission;
        $this->batch = $batch;
    }

    public function runValidation(): void
    {
        $this->authorizePermission($this->requiredPermission);

        try {
            $this->batch = app(ValidateImportBatchAction::class)->execute(new ValidateImportBatchData($this->batch->id));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function approveAndImport(): void
    {
        $this->authorizePermission($this->requiredPermission);

        try {
            $this->batch = app(ExecuteImportBatchAction::class)->execute(new ExecuteImportBatchData($this->batch->id, approved: true));
            $this->toast($this->batch->isDryRun() ? __('Dry run complete — nothing was written.') : __('Import complete.'));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function rollback(): void
    {
        $this->authorizePermission('core.import.rollback');

        try {
            $this->batch = app(RollbackImportBatchAction::class)->execute(new RollbackImportBatchData($this->batch->id));
            $this->toast(__('Batch rolled back.'));
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'danger');
        }
    }

    public function downloadCorrectionFile(): ?Response
    {
        $this->authorizePermission($this->requiredPermission);

        $fileId = $this->batch->error_file_id;
        abort_if($fileId === null, 404);

        $file = $this->batch->errorFile()->firstOrFail();

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
        return view('core::imports.batch', [
            'definition' => ImportDefinition::where('key', $this->batch->definition_key)->first(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Imports;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Imports\RollbackImportBatchData;
use Modules\Core\Domain\Exceptions\ImportNotRollbackableException;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\ImportRow;

/**
 * ACT-RollbackImportBatch (Book A CORE-11 BR-CORE-11-007/AC-CORE-11-004).
 * All-or-nothing: the whole rollback runs in one transaction, so if any
 * row's `rollbackRow()` refuses (a created record was since modified
 * or referenced — the concrete importer's own call to make, this
 * framework has no generic way to know), nothing already rolled back
 * in this run is left half-undone.
 */
final class RollbackImportBatchAction extends Action
{
    public function execute(RollbackImportBatchData $data): ImportBatch
    {
        $batch = ImportBatch::query()->findOrFail($data->batchId);
        $definition = ImporterRegistry::get($batch->definition_key);

        if ($definition === null || ! $definition->isRollbackable) {
            throw new ImportNotRollbackableException("Import [{$batch->definition_key}] is not rollbackable.");
        }

        if ($batch->status !== 'completed') {
            throw new ImportNotRollbackableException("Only a completed batch can be rolled back; this one is [{$batch->status}].");
        }

        $importer = ImporterRegistry::resolve($batch->definition_key);

        return $this->transaction(function () use ($batch, $importer): ImportBatch {
            ImportRow::where('batch_id', $batch->id)
                ->where('status', 'imported')
                ->whereNotNull('created_id')
                ->each(function (ImportRow $row) use ($importer): void {
                    $importer->rollbackRow($row);
                    $row->forceFill(['status' => 'pending', 'created_type' => null, 'created_id' => null])->save();
                });

            $batch->forceFill(['status' => 'rolled_back', 'rolled_back_at' => Carbon::now()])->save();

            return $batch;
        });
    }
}

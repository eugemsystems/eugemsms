<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Imports;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Imports\ExecuteImportBatchData;
use Modules\Core\Domain\DataObjects\Imports\ImportContext;
use Modules\Core\Domain\Exceptions\BatchNotValidatedException;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\ImportRow;

/**
 * ACT-ExecuteImportBatch (Book A CORE-11 §4 ⭐/BR-CORE-11-003/005/006
 * /009). Requires an explicit `approved` flag — there is no one-click
 * import (BR-CORE-11-003), the caller is the screen that showed the
 * validation report and got the user's confirmation. Only `valid` rows
 * are ever processed; `invalid` ones stay excluded, exactly as the
 * validation report already promised.
 */
final class ExecuteImportBatchAction extends Action
{
    public function execute(ExecuteImportBatchData $data): ImportBatch
    {
        $batch = ImportBatch::query()->findOrFail($data->batchId);

        if ($batch->status !== 'validated') {
            throw new BatchNotValidatedException("This batch is [{$batch->status}]; it must be validated before it can be imported.");
        }

        if (! $data->approved) {
            throw new BatchNotValidatedException('The validation report must be explicitly approved before import proceeds.');
        }

        PeriodGuard::assertWritable($batch);

        if ($batch->isDryRun()) {
            return $batch;
        }

        $importer = ImporterRegistry::resolve($batch->definition_key);
        $context = new ImportContext(
            schoolId: $batch->school_id,
            batchId: $batch->id,
            duplicateStrategy: $batch->duplicateStrategy(),
            importedByUserId: $batch->imported_by,
            academicYearId: $batch->academic_year_id,
            termId: $batch->term_id,
        );

        return $this->transaction(function () use ($batch, $importer, $context): ImportBatch {
            $batch->forceFill(['status' => 'importing', 'started_at' => Carbon::now()])->save();

            $imported = 0;
            $skipped = 0;
            $failed = 0;

            ImportRow::where('batch_id', $batch->id)->where('status', 'valid')->each(function (ImportRow $row) use ($importer, $context, &$imported, &$skipped, &$failed): void {
                $existing = $importer->findExisting($row->mapped_data);

                if ($existing !== null && $context->duplicateStrategy === 'skip') {
                    $row->forceFill(['status' => 'skipped'])->save();
                    $skipped++;

                    return;
                }

                $result = $importer->import($row->mapped_data, $context);

                $row->forceFill([
                    'status' => $result->status,
                    'created_type' => $result->createdType,
                    'created_id' => $result->createdId,
                    'errors' => $result->message !== null ? [$result->message] : null,
                ])->save();

                match ($result->status) {
                    'imported' => $imported++,
                    'skipped' => $skipped++,
                    default => $failed++,
                };
            });

            $batch->forceFill([
                'status' => 'completed',
                'imported_rows' => $imported,
                'skipped_rows' => $skipped,
                'failed_rows' => $failed,
                'completed_at' => Carbon::now(),
            ])->save();

            return $batch;
        });
    }
}

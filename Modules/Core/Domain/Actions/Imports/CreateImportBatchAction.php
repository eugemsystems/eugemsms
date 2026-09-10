<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Imports;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Imports\CreateImportBatchData;
use Modules\Core\Domain\Exceptions\UnmetImportDependencyException;
use Modules\Core\Domain\Exceptions\UnregisteredImporterException;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Core\Models\ImportBatch;

/**
 * ACT-CreateImportBatch (Book A CORE-11 §2/BR-CORE-11-008/011). Checks
 * every declared dependency has a completed batch for this school
 * first (AC-CORE-11-005), then the period guard, before a single row
 * is even read.
 */
final class CreateImportBatchAction extends Action
{
    public function execute(CreateImportBatchData $data): ImportBatch
    {
        $definition = ImporterRegistry::get($data->definitionKey)
            ?? throw new UnregisteredImporterException("Import definition [{$data->definitionKey}] is not registered.", ['key' => $data->definitionKey]);

        foreach ($definition->dependsOn as $dependencyKey) {
            $satisfied = ImportBatch::where('school_id', $data->schoolId)
                ->where('definition_key', $dependencyKey)
                ->where('status', 'completed')
                ->exists();

            if (! $satisfied) {
                throw new UnmetImportDependencyException(
                    "Import [{$data->definitionKey}] depends on [{$dependencyKey}] completing first.",
                    ['definition' => $data->definitionKey, 'depends_on' => $dependencyKey],
                );
            }
        }

        return $this->transaction(function () use ($data): ImportBatch {
            $batch = ImportBatch::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'definition_key' => $data->definitionKey,
                'source_file_id' => $data->sourceFileId,
                'column_mapping' => $data->columnMapping,
                'options' => ['duplicate_strategy' => $data->duplicateStrategy, 'dry_run' => $data->dryRun],
                'status' => 'mapping',
                'imported_by' => $data->importedByUserId,
            ]);

            PeriodGuard::assertWritable($batch);

            return $batch;
        });
    }
}

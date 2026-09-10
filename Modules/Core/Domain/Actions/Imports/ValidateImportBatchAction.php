<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Imports;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Files\UploadFileAction;
use Modules\Core\Domain\DataObjects\Files\UploadFileData;
use Modules\Core\Domain\DataObjects\Imports\ValidateImportBatchData;
use Modules\Core\Domain\Registry\ImporterRegistry;
use Modules\Core\Domain\Support\Imports\CsvReader;
use Modules\Core\Domain\Support\Imports\CsvWriter;
use Modules\Core\Domain\Support\PeriodGuard;
use Modules\Core\Models\ImportBatch;
use Modules\Core\Models\ImportRow;

/**
 * ACT-ValidateImportBatch (Book A CORE-11 §4 ⭐/BR-CORE-11-001/002/004
 * /AC-CORE-11-001). Runs a full pass over EVERY row before a single
 * record is written — nothing is imported here, only validated.
 */
final class ValidateImportBatchAction extends Action
{
    public function __construct(
        private readonly CsvReader $csvReader,
        private readonly CsvWriter $csvWriter,
        private readonly UploadFileAction $uploadFile,
    ) {}

    public function execute(ValidateImportBatchData $data): ImportBatch
    {
        $batch = ImportBatch::query()->with('sourceFile')->findOrFail($data->batchId);
        PeriodGuard::assertWritable($batch);

        $importer = ImporterRegistry::resolve($batch->definition_key);
        $rules = $importer->rules();

        $contents = Storage::disk($batch->sourceFile->disk)->get($batch->sourceFile->path);
        $rawRows = $this->csvReader->parse((string) $contents);

        return $this->transaction(function () use ($batch, $importer, $rules, $rawRows): ImportBatch {
            $batch->forceFill(['status' => 'validating'])->save();

            $invalidGroups = [];
            $validCount = 0;
            $invalidCount = 0;
            $correctionRows = [];

            foreach ($rawRows as $rowNumber => $raw) {
                $mapped = [];

                foreach ($batch->column_mapping as $column => $sourceHeader) {
                    $mapped[$column] = $raw[$sourceHeader] ?? null;
                }

                $transformed = $importer->transform($mapped);
                $validator = Validator::make($transformed, $rules);

                $row = ImportRow::updateOrCreate(
                    ['batch_id' => $batch->id, 'row_number' => $rowNumber],
                    ['raw_data' => $raw, 'mapped_data' => $transformed],
                );

                if ($validator->fails()) {
                    $errors = $validator->errors()->all();
                    $row->forceFill(['status' => 'invalid', 'errors' => $errors])->save();
                    $invalidCount++;

                    foreach ($validator->errors()->messages() as $field => $messages) {
                        foreach ($messages as $message) {
                            $invalidGroups[$message][] = $rowNumber;
                        }
                    }

                    $correctionRows[] = [...$raw, 'errors' => implode('; ', $errors)];
                } else {
                    $row->forceFill(['status' => 'valid'])->save();
                    $validCount++;
                }
            }

            $errorFileId = null;

            if ($correctionRows !== []) {
                $csv = $this->csvWriter->write($correctionRows);
                $correctionFile = $this->uploadFile->execute(new UploadFileData(
                    schoolId: $batch->school_id,
                    category: 'import_correction_file',
                    contents: $csv,
                    originalName: "correction-{$batch->ulid}.csv",
                    uploadedByUserId: $batch->imported_by,
                    attachableType: ImportBatch::class,
                    attachableId: $batch->id,
                ));
                $errorFileId = $correctionFile->id;
            }

            $batch->forceFill([
                'status' => 'validated',
                'total_rows' => count($rawRows),
                'valid_rows' => $validCount,
                'invalid_rows' => $invalidCount,
                'validation_report' => [
                    'total' => count($rawRows),
                    'valid' => $validCount,
                    'invalid' => $invalidCount,
                    'errors_by_type' => array_map(
                        fn (array $rows, string $message): array => ['message' => $message, 'rows' => $rows],
                        $invalidGroups,
                        array_keys($invalidGroups),
                    ),
                ],
                'error_file_id' => $errorFileId,
            ])->save();

            return $batch;
        });
    }
}

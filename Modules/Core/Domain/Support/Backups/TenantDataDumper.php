<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Backups;

use Illuminate\Support\Facades\Storage;
use Modules\Core\Domain\Registry\TenantModelRegistry;
use Modules\Core\Models\File;
use Throwable;

/**
 * Book A CORE-13 §2/BR-CORE-13-007. Dumps one school's own tenant-scoped
 * rows and file bytes via `TenantModelRegistry` — the same registry the
 * tenancy isolation test generator uses, so any model missing from it is
 * already a tracked gap, not a silent omission specific to this class.
 * Extracted from `CreateBackupAction`'s own school-scoped dump so
 * `GenerateContractExitExportAction` (BR-CORE-13-008, open-format
 * export) can reuse identical logic instead of re-deriving it — the two
 * differ only in what they DO with the dump (encrypt into one blob vs.
 * write open CSV/JSON files), not in what data they collect.
 */
final class TenantDataDumper
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function dumpTables(int $schoolId): array
    {
        $dump = [];

        foreach (array_keys(TenantModelRegistry::all()) as $modelClass) {
            $model = new $modelClass;

            $dump[$model->getTable()] = $modelClass::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->get()
                ->map(fn ($row): array => $row->toArray())
                ->all();
        }

        return $dump;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function dumpFiles(int $schoolId): array
    {
        $dump = [];

        foreach (File::query()->where('school_id', $schoolId)->get() as $file) {
            try {
                $contents = Storage::disk($file->disk)->get($file->path);
            } catch (Throwable) {
                continue;
            }

            if ($contents === null) {
                continue;
            }

            $dump[$file->ulid] = [
                'original_name' => $file->original_name,
                'mime_type' => $file->mime_type,
                'school_id' => $file->school_id,
                'contents_base64' => base64_encode($contents),
            ];
        }

        return $dump;
    }
}

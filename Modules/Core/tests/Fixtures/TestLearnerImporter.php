<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Contracts\Imports\Importer;
use Modules\Core\Domain\DataObjects\Imports\ImportContext;
use Modules\Core\Domain\DataObjects\Imports\ImportRowResult;
use Modules\Core\Domain\DataObjects\Imports\TemplateColumn;
use Modules\Core\Models\ImportRow;

/**
 * A minimal `Importer` for CORE-11's tests, creating `House` rows (the
 * same "real table, no dedicated fixture migration" trick as
 * `TestApprovable`/`TestAuditableHouse`) — nothing about "houses" is
 * meaningful here, it's standing in for a real entity like a learner.
 */
final class TestLearnerImporter implements Importer
{
    public function __construct(
        private readonly int $schoolId,
    ) {}

    public function key(): string
    {
        return 'test_learner';
    }

    public function templateColumns(): array
    {
        return [
            new TemplateColumn('name', 'Jane Doe', required: true),
            new TemplateColumn('code', 'JD001', required: true, notes: 'Unique per school'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ];
    }

    public function transform(array $row): array
    {
        return [
            'name' => trim((string) ($row['name'] ?? '')),
            'code' => strtoupper(trim((string) ($row['code'] ?? ''))),
        ];
    }

    public function findExisting(array $row): ?Model
    {
        return TestApprovable::withoutGlobalScopes()
            ->where('school_id', $this->schoolId)
            ->where('code', $row['code'])
            ->first();
    }

    public function import(array $row, ImportContext $context): ImportRowResult
    {
        $existing = $this->findExisting($row);

        if ($existing !== null) {
            if ($context->duplicateStrategy === 'update') {
                $existing->forceFill(['name' => $row['name']])->save();

                return new ImportRowResult('imported', $existing::class, $existing->id);
            }

            return new ImportRowResult('skipped');
        }

        $created = TestApprovable::create([
            'school_id' => $context->schoolId,
            'code' => $row['code'],
            'name' => $row['name'],
        ]);

        return new ImportRowResult('imported', $created::class, $created->id);
    }

    public function rollbackRow(ImportRow $row): void
    {
        TestApprovable::withoutGlobalScopes()->whereKey($row->created_id)->delete();
    }
}

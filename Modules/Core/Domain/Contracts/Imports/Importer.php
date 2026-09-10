<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Imports;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\DataObjects\Imports\ImportContext;
use Modules\Core\Domain\DataObjects\Imports\ImportRowResult;
use Modules\Core\Domain\DataObjects\Imports\TemplateColumn;
use Modules\Core\Models\ImportRow;

/**
 * Book A CORE-11 §3. Every concrete entity importer (students,
 * guardians, opening balances, ...) lives with its owning module and
 * implements this — CORE-11 itself owns only the framework around it.
 */
interface Importer
{
    public function key(): string;

    /**
     * @return array<int, TemplateColumn>
     */
    public function templateColumns(): array;

    /**
     * @return array<string, array<int, string>|string> Laravel validation rules, keyed by mapped column
     */
    public function rules(): array;

    /**
     * @param  array<string, mixed>  $row  mapped column => raw value
     * @return array<string, mixed> normalised (dates, phones, casing)
     */
    public function transform(array $row): array;

    /**
     * @param  array<string, mixed>  $row  transformed row
     */
    public function findExisting(array $row): ?Model;

    /**
     * @param  array<string, mixed>  $row  transformed row
     */
    public function import(array $row, ImportContext $context): ImportRowResult;

    public function rollbackRow(ImportRow $row): void;
}

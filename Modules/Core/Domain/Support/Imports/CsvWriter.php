<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Imports;

final class CsvWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function write(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, array_keys($rows[array_key_first($rows)]));

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (mixed $value): string => (string) $value, $row));
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents !== false ? $contents : '';
    }
}

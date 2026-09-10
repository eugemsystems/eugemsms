<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Imports;

/**
 * Book A CORE-11. CSV only for this engine pass — spreadsheet (XLSX)
 * parsing needs a new dependency (PhpSpreadsheet), deferred until
 * that's approved. PHP's own `str_getcsv` needs no dependency at all.
 */
final class CsvReader
{
    /**
     * @return array<int, array<string, string>> row number (1-based, header excluded) => header => value
     */
    public function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents));

        if ($lines === false || $lines === []) {
            return [];
        }

        $header = str_getcsv(array_shift($lines));
        $rows = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $values = array_slice(array_pad($values, count($header), null), 0, count($header));
            $rows[$index + 1] = array_combine($header, $values);
        }

        return $rows;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

use Modules\Core\Models\NumberingSeries;

/**
 * Book A CORE-06 §3. Expands a series' `pattern` — placeholders
 * `{SCHOOL}`, `{TYPE}`, `{YEAR}`, `{TERM}`, `{SEQ:n}` (n = zero-pad
 * width) — into the final `formatted_number`.
 */
final class NumberPatternFormatter
{
    public function format(NumberingSeries $series, int $sequence): string
    {
        $replacements = [
            '{SCHOOL}' => $series->school->code,
            '{TYPE}' => strtoupper($series->document_type),
            '{YEAR}' => $series->academicYear !== null ? $series->academicYear->name : '',
            '{TERM}' => $series->term !== null ? (string) $series->term->number : '',
        ];

        $formatted = strtr($series->pattern, $replacements);
        $formatted = (string) preg_replace_callback(
            '/\{SEQ:(\d+)\}/',
            fn (array $matches): string => str_pad((string) $sequence, (int) $matches[1], '0', STR_PAD_LEFT),
            $formatted,
        );

        if ($series->prefix !== null) {
            $formatted = $series->prefix.$formatted;
        }

        // Collapse a placeholder that expanded to '' (no term on a
        // termly series, for instance) so the number doesn't end up
        // with a dangling '//'.
        return (string) preg_replace('#/+#', '/', trim($formatted, '/'));
    }
}

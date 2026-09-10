<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Support;

use Illuminate\Support\Collection;
use Modules\Comms\Models\RuleTemplateVariant;

/**
 * Book I COM-02 §4/BR-COM-02-009 (AC-COM-02-006). Splits by configured
 * weight, DETERMINISTICALLY per subject — `crc32("{ruleId}:{subjectId}")`
 * always lands in the same weighted bucket for the same pair, so the
 * same invoice gets the same variant across every repeated scan.
 */
final class VariantPicker
{
    /**
     * @param  Collection<int, RuleTemplateVariant>  $variants
     */
    public function pick(Collection $variants, int $ruleId, int $subjectId): ?RuleTemplateVariant
    {
        if ($variants->isEmpty()) {
            return null;
        }

        $bucket = crc32("{$ruleId}:{$subjectId}") % 100;
        $cumulative = 0;

        foreach ($variants->sortBy('variant_key') as $variant) {
            $cumulative += $variant->weight_percent;

            if ($bucket < $cumulative) {
                return $variant;
            }
        }

        return $variants->sortBy('variant_key')->last();
    }
}

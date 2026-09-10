<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\UpdateNumberingSeriesData;
use Modules\Core\Domain\Exceptions\SeriesPatternLockedException;
use Modules\Core\Models\NumberingSeries;

/**
 * ACT-UpdateNumberingSeries (Book A CORE-06 BR-CORE-06-005): the
 * pattern cannot change once a number has been allocated in the
 * current period (`next_sequence > 1`) — everything else (activation,
 * reset policy for the *next* period, prefix/padding) may change
 * freely.
 */
final class UpdateNumberingSeriesAction extends Action
{
    public function execute(UpdateNumberingSeriesData $data): NumberingSeries
    {
        $series = NumberingSeries::withoutGlobalScopes()->findOrFail($data->seriesId);

        if ($data->pattern !== null && $data->pattern !== $series->pattern && $series->next_sequence > 1) {
            throw new SeriesPatternLockedException(
                'This series has already allocated numbers this period. The pattern takes effect from the next reset boundary.',
            );
        }

        return $this->transaction(function () use ($series, $data): NumberingSeries {
            $series->forceFill(array_filter([
                'pattern' => $data->pattern,
                'prefix' => $data->prefix,
                'sequence_padding' => $data->sequencePadding,
                'reset_policy' => $data->resetPolicy,
                'is_active' => $data->isActive,
            ], fn ($value): bool => $value !== null))->save();

            return $series;
        });
    }
}

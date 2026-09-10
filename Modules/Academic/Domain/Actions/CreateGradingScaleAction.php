<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Academic\Domain\DataObjects\CreateGradingScaleData;
use Modules\Academic\Domain\DataObjects\GradeBandInput;
use Modules\Academic\Domain\Exceptions\GradeBandGapOrOverlapException;
use Modules\Academic\Models\GradeBand;
use Modules\Academic\Models\GradingScale;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateGradingScale (Book D ACA-05 §2/§5/BR-ACA-05-001/002).
 * Takes the scale and its complete set of bands together — the spec's
 * own contiguity rule is checked "when the scale is saved"
 * (AC-ACA-05-008), and a partial set of bands can never span 0–100,
 * so there is no sound way to validate contiguity incrementally as
 * each band is added one at a time.
 *
 * Contiguity (BR-ACA-05-002) means every band touches the next
 * exactly, edge to edge: sorted by `min_percent`, the lowest band
 * starts at 0, the highest ends at 100, and each band's `max_percent`
 * equals the next band's `min_percent`. `GradeBand::bandFor()` reads
 * `max_percent` as exclusive except on the top band, so a value
 * exactly on a shared boundary belongs to the band above it, never
 * both.
 */
final class CreateGradingScaleAction extends Action
{
    public function execute(CreateGradingScaleData $data): GradingScale
    {
        $sorted = collect($data->bands)->sortBy(fn (GradeBandInput $b): float => $b->minPercent)->values();

        $this->assertContiguous($sorted);

        return $this->transaction(function () use ($data, $sorted): GradingScale {
            $scale = GradingScale::create([
                'school_id' => $data->schoolId,
                'framework_id' => $data->frameworkId,
                'code' => $data->code,
                'name' => $data->name,
                'scale_type' => $data->scaleType,
                'lower_is_better' => $data->lowerIsBetter,
                'pass_grade' => $data->passGrade,
                'is_default_for_level' => $data->isDefaultForLevel,
                'is_active' => true,
            ]);

            foreach ($sorted as $index => $band) {
                GradeBand::create([
                    'school_id' => $data->schoolId,
                    'grading_scale_id' => $scale->id,
                    'grade' => $band->grade,
                    'descriptor' => $band->descriptor,
                    'min_percent' => $band->minPercent,
                    'max_percent' => $band->maxPercent,
                    'points' => $band->points,
                    'is_pass' => $band->isPass,
                    'colour' => $band->colour,
                    'sort_order' => $index + 1,
                ]);
            }

            return $scale;
        });
    }

    /**
     * @param  Collection<int, GradeBandInput>  $sorted
     */
    private function assertContiguous($sorted): void
    {
        if ($sorted->isEmpty()) {
            return;
        }

        $first = $sorted->first();

        if (abs($first->minPercent - 0.0) > 0.001) {
            throw GradeBandGapOrOverlapException::doesNotStartAtZero(number_format($first->minPercent, 2));
        }

        $previous = null;

        foreach ($sorted as $band) {
            if ($previous !== null && abs($previous->maxPercent - $band->minPercent) > 0.001) {
                throw GradeBandGapOrOverlapException::forGap(
                    number_format($previous->maxPercent, 2),
                    number_format($band->minPercent, 2),
                );
            }

            $previous = $band;
        }

        $last = $sorted->last();

        if (abs($last->maxPercent - 100.0) > 0.001) {
            throw GradeBandGapOrOverlapException::doesNotEndAt100(number_format($last->maxPercent, 2));
        }
    }
}

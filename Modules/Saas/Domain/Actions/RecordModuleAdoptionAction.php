<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Domain\Registry\ModuleAdoptionSignalRegistry;
use Modules\Saas\Models\ModuleAdoptionScore;

/**
 * ACT-RecordModuleAdoption (Book J SAA-03 §3 ⭐/§4/BR-SAA-03-006
 * ⭐ (AC-SAA-03-002)). Meant to run nightly per school — the same
 * "bookkeeping real, wiring deferred" boundary this book set already
 * draws. Every registered signal is counted for real against its
 * owning module's own table; nothing here is a self-report.
 */
final class RecordModuleAdoptionAction extends Action
{
    /**
     * @return array<int, ModuleAdoptionScore>
     */
    public function execute(int $schoolId, ?string $periodMonth = null): array
    {
        $periodMonth ??= Carbon::today()->format('Y-m');
        $scores = [];

        foreach (ModuleAdoptionSignalRegistry::all() as $signal) {
            $count = ($signal->resolver)($schoolId, $periodMonth);

            $scores[] = $this->transaction(fn (): ModuleAdoptionScore => ModuleAdoptionScore::updateOrCreate(
                ['school_id' => $schoolId, 'module_code' => $signal->moduleCode, 'period_month' => $periodMonth],
                [
                    'activity_signal' => $signal->activitySignal,
                    'activity_count' => $count,
                    'is_actively_used' => $count >= $signal->activeThreshold,
                ],
            ));
        }

        return $scores;
    }
}

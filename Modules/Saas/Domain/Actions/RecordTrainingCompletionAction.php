<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Saas\Models\TrainingCompletion;

/**
 * ACT-RecordTrainingCompletion (Book J SAA-03 §2).
 */
final class RecordTrainingCompletionAction extends Action
{
    public function execute(int $schoolId, int $userId, string $materialKey): TrainingCompletion
    {
        return $this->transaction(fn (): TrainingCompletion => TrainingCompletion::create([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'material_key' => $materialKey,
            'completed_at' => Carbon::now(),
        ]));
    }
}

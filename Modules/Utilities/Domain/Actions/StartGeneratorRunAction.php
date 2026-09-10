<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Utilities\Domain\DataObjects\StartGeneratorRunData;
use Modules\Utilities\Domain\Events\GeneratorStarted;
use Modules\Utilities\Models\Generator;
use Modules\Utilities\Models\GeneratorRun;

/**
 * ACT-StartGeneratorRun (Book H2 OPS-04 §2/BR-OPS-04-013).
 */
final class StartGeneratorRunAction extends Action
{
    public function execute(StartGeneratorRunData $data): GeneratorRun
    {
        $generator = Generator::findOrFail($data->generatorId);

        return $this->transaction(function () use ($data, $generator): GeneratorRun {
            $run = GeneratorRun::create([
                'school_id' => $data->schoolId,
                'term_id' => $data->termId,
                'generator_id' => $generator->id,
                'run_date' => $data->startedAt->toDateString(),
                'started_at' => $data->startedAt,
                'start_hour_meter' => $generator->current_hours,
                'reason' => $data->reason,
                'load_shedding_stage' => $data->loadSheddingStage,
                'operated_by' => $data->operatedByUserId,
                'is_anomaly' => false,
            ]);

            $generator->update(['status' => 'operational']);

            event(new GeneratorStarted($run));

            return $run;
        });
    }
}

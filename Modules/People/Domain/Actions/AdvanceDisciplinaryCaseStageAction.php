<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\AdvanceDisciplinaryCaseStageData;
use Modules\People\Models\StaffDisciplinaryCase;

/**
 * ACT-AdvanceDisciplinaryCaseStage (Book C PPL-04 §4/BR-PPL-04-020).
 */
final class AdvanceDisciplinaryCaseStageAction extends Action
{
    /**
     * @var array<string, array<int, string>>
     */
    private const array ALLOWED_TRANSITIONS = [
        'reported' => ['investigation'],
        'investigation' => ['hearing'],
        'hearing' => ['decided'],
        'decided' => ['appealed', 'closed'],
        'appealed' => ['closed'],
    ];

    public function execute(AdvanceDisciplinaryCaseStageData $data): StaffDisciplinaryCase
    {
        $case = StaffDisciplinaryCase::findOrFail($data->caseId);
        $allowed = self::ALLOWED_TRANSITIONS[$case->stage] ?? [];

        if (! in_array($data->targetStage, $allowed, true)) {
            throw new InvalidStateTransitionException(
                "A disciplinary case in [{$case->stage}] cannot move to [{$data->targetStage}].",
                ['from' => $case->stage, 'to' => $data->targetStage],
            );
        }

        if ($data->targetStage === 'decided' && ($data->outcome === null || $data->outcomeDate === null)) {
            throw new InvalidArgumentException('An outcome and outcome date are required when a case moves to [decided].');
        }

        return $this->transaction(function () use ($case, $data): StaffDisciplinaryCase {
            $case->update([
                'stage' => $data->targetStage,
                'outcome' => $data->outcome ?? $case->outcome,
                'outcome_date' => $data->outcomeDate?->toDateString() ?? $case->outcome_date,
            ]);

            return $case;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ComputeItemAnalysisData;
use Modules\Academic\Domain\Support\ItemAnalysisCalculator;
use Modules\Academic\Models\CbtTest;
use Modules\Academic\Models\QuestionBankItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ComputeItemAnalysis (Book K ACA-09 §4/BR-ACA-09-009). Runs after
 * a test closes, from actual candidate performance, and updates the
 * question bank so future rule-based assembly can weight toward
 * well-discriminating items.
 */
final class ComputeItemAnalysisAction extends Action
{
    public function __construct(
        private readonly ItemAnalysisCalculator $calculator,
    ) {}

    /**
     * @return array<int, array{questionId: int, difficultyIndex: float, discriminationIndex: float}>
     */
    public function execute(ComputeItemAnalysisData $data): array
    {
        $test = CbtTest::findOrFail($data->testId);

        if (! in_array($test->status, ['closed', 'results_released'], true)) {
            throw new InvalidStateTransitionException(
                "Item analysis can only run once a CBT test has closed (currently [{$test->status}]).",
                ['test_id' => $test->id, 'status' => $test->status],
            );
        }

        $results = $this->calculator->compute($test);

        $this->transaction(function () use ($results): void {
            foreach ($results as $result) {
                QuestionBankItem::query()->whereKey($result['questionId'])->update([
                    'difficulty_index' => $result['difficultyIndex'],
                    'discrimination_index' => $result['discriminationIndex'],
                ]);
            }
        });

        return $results;
    }
}

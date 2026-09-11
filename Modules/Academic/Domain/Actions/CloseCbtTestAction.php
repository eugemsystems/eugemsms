<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CloseCbtTestData;
use Modules\Academic\Models\CbtTest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-CloseCbtTest (Book K ACA-09 §4/BR-ACA-09-009). Closing is the
 * gate item analysis waits behind — it runs from actual candidate
 * performance once no more attempts can start.
 */
final class CloseCbtTestAction extends Action
{
    public function execute(CloseCbtTestData $data): CbtTest
    {
        $test = CbtTest::findOrFail($data->testId);

        if (! in_array($test->status, ['scheduled', 'open'], true)) {
            throw new InvalidStateTransitionException(
                "A CBT test in [{$test->status}] cannot be closed.",
                ['test_id' => $test->id, 'status' => $test->status],
            );
        }

        return $this->transaction(function () use ($test): CbtTest {
            $test->update(['status' => 'closed']);

            return $test->fresh();
        });
    }
}

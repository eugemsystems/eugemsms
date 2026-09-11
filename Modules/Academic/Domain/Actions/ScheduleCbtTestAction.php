<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ScheduleCbtTestData;
use Modules\Academic\Models\CbtTest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ScheduleCbtTest (Book K ACA-09 §2/BR-ACA-09-011). Once
 * scheduled, the question set is locked — this module builds no
 * "edit a scheduled test" action, so the only way to change the
 * question set again is to build a new test.
 */
final class ScheduleCbtTestAction extends Action
{
    public function execute(ScheduleCbtTestData $data): CbtTest
    {
        $test = CbtTest::findOrFail($data->testId);

        if ($test->status !== 'draft') {
            throw new InvalidStateTransitionException(
                "A CBT test in [{$test->status}] cannot be scheduled.",
                ['test_id' => $test->id, 'status' => $test->status],
            );
        }

        return $this->transaction(function () use ($test): CbtTest {
            $test->update(['status' => 'scheduled']);

            return $test->fresh();
        });
    }
}

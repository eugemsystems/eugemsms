<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\SubmitAttemptData;
use Modules\Academic\Models\CbtCandidateAttempt;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AutoSubmitExpiredAttempts (Book K ACA-09 §3/AC-ACA-09-006). A test
 * that reaches its time limit auto-submits with whatever was saved; a
 * candidate who never came back would otherwise stay "in progress" forever.
 * Expiry is judged by the server clock only (BR-ACA-09-002). Meant to run
 * on a schedule — the monitor screen also triggers it on demand.
 */
final class AutoSubmitExpiredAttemptsAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly SubmitAttemptAction $submitAttempt,
    ) {}

    /**
     * @return int how many attempts were submitted
     */
    public function execute(int $testId): int
    {
        $submitted = 0;

        CbtCandidateAttempt::query()->where('test_id', $testId)->whereIn('status', ['in_progress', 'flagged'])->get()
            ->each(function (CbtCandidateAttempt $attempt) use (&$submitted): void {
                if ($attempt->remainingSeconds() > 0) {
                    return;
                }

                $this->submitAttempt->execute(new SubmitAttemptData($attempt->id, autoSubmitted: true));
                $submitted++;
            });

        return $submitted;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\DeclareTillCountData;
use Modules\Finance\Models\TillSession;

/**
 * ACT-DeclareTillCount (Book B FIN-04 §3 ⭐/BR-FIN-04-003). The blind
 * half of the cash-up — the cashier's physical count is recorded
 * before `expected_closing` is ever computed, let alone shown.
 */
final class DeclareTillCountAction extends Action
{
    public function execute(DeclareTillCountData $data): TillSession
    {
        $session = TillSession::findOrFail($data->tillSessionId);

        if ($session->status !== 'open') {
            throw new InvalidStateTransitionException(
                "A till session can only be declared from [open]; this one is [{$session->status}].",
                ['status' => $session->status],
            );
        }

        return $this->transaction(function () use ($session, $data): TillSession {
            $session->update([
                'declared_closing' => $data->declaredClosing,
                'status' => 'declaring',
            ]);

            return $session;
        });
    }
}

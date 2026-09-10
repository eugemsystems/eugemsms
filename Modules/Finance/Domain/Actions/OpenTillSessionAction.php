<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\OpenTillSessionData;
use Modules\Finance\Domain\Events\TillSessionOpened;
use Modules\Finance\Domain\Exceptions\TillSessionAlreadyOpenException;
use Modules\Finance\Models\TillSession;

/**
 * ACT-OpenTillSession (Book B FIN-04 §3/BR-FIN-04-002). Posts nothing
 * — the opening float is a transfer within cash accounts, not a
 * financial event.
 */
final class OpenTillSessionAction extends Action
{
    public function execute(OpenTillSessionData $data): TillSession
    {
        $alreadyOpen = TillSession::query()
            ->where('cashier_id', $data->cashierId)
            ->where('status', 'open')
            ->exists();

        if ($alreadyOpen) {
            throw TillSessionAlreadyOpenException::forCashier($data->cashierId);
        }

        return $this->transaction(function () use ($data): TillSession {
            $session = TillSession::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'till_id' => $data->tillId,
                'cashier_id' => $data->cashierId,
                'session_number' => 'TS/'.Str::upper(Str::random(10)),
                'opened_at' => Carbon::now(),
                'opening_float' => $data->openingFloat,
                'status' => 'open',
            ]);

            event(new TillSessionOpened($session));

            return $session;
        });
    }
}

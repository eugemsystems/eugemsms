<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\RunReconciliationData;
use Modules\Finance\Domain\Events\ReconciliationRunCompleted;
use Modules\Finance\Models\BankStatementLine;
use Modules\Finance\Models\PaymentIntent;
use Modules\Finance\Models\ReconciliationRun;

/**
 * ACT-RunReconciliation (Book B FIN-05 §5 ⭐/BR-FIN-05-013,
 * AC-FIN-05-007). Never auto-resolves — this action only ever
 * produces the exception list; a human clears each one elsewhere.
 *
 * A real four-way reconciliation cross-checks gateway, receipts, bank,
 * and GL. This pass builds the gateway-vs-receipts side in full
 * (`GATEWAY_NO_RECEIPT`/`RECEIPT_NO_GATEWAY`/`AMOUNT_MISMATCH`/
 * `DUPLICATE_SETTLEMENT`, from `gatewaySettlements` — which stands in
 * for a real settlement-report API call no live gateway integration
 * exists to make) and the bank side's `BANK_NO_RECEIPT`. `RECEIPT_NO_BANK`,
 * `GL_VARIANCE`, and `FEE_UNPOSTED` are documented as deferred rather
 * than approximated — each needs a cross-check this pass's scope
 * doesn't reach (matching receipts back to statement lines in reverse,
 * a real GL balance comparison, and fee-journal presence checking).
 */
final class RunReconciliationAction extends Action
{
    public function execute(RunReconciliationData $data): ReconciliationRun
    {
        return $this->transaction(function () use ($data): ReconciliationRun {
            $exceptions = [];

            $exceptions = [...$exceptions, ...$this->gatewayExceptions($data)];
            $exceptions = [...$exceptions, ...$this->bankExceptions($data)];

            $status = $exceptions === [] ? 'clean' : 'exceptions';

            $run = ReconciliationRun::create([
                'school_id' => $data->schoolId,
                'run_date' => $data->runDate->toDateString(),
                'scope' => $data->scope,
                'gateway_id' => $data->gatewayId,
                'bank_account_id' => $data->bankAccountId,
                'currency' => $data->currency,
                'exception_count' => count($exceptions),
                'exceptions' => $exceptions,
                'status' => $status,
                'ran_at' => Carbon::now(),
            ]);

            event(new ReconciliationRunCompleted($run));

            return $run;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gatewayExceptions(RunReconciliationData $data): array
    {
        if ($data->gatewayId === null) {
            return [];
        }

        $exceptions = [];
        $seenReferences = [];

        $succeededIntents = PaymentIntent::query()
            ->where('school_id', $data->schoolId)
            ->where('gateway_id', $data->gatewayId)
            ->where('status', 'succeeded')
            ->whereDate('completed_at', $data->runDate)
            ->get()
            ->keyBy('gateway_reference');

        foreach ($data->gatewaySettlements as $settlement) {
            $reference = $settlement['gateway_reference'];

            if (isset($seenReferences[$reference])) {
                $exceptions[] = ['class' => 'DUPLICATE_SETTLEMENT', 'gateway_reference' => $reference];

                continue;
            }

            $seenReferences[$reference] = true;
            $intent = $succeededIntents->get($reference);

            if ($intent === null) {
                $exceptions[] = ['class' => 'GATEWAY_NO_RECEIPT', 'gateway_reference' => $reference, 'amount_minor' => $settlement['amount_minor']];

                continue;
            }

            if ((int) $intent->amount_minor !== (int) $settlement['amount_minor']) {
                $exceptions[] = [
                    'class' => 'AMOUNT_MISMATCH',
                    'gateway_reference' => $reference,
                    'gateway_amount_minor' => $settlement['amount_minor'],
                    'receipt_amount_minor' => $intent->amount_minor,
                ];
            }
        }

        foreach ($succeededIntents as $reference => $intent) {
            if (! isset($seenReferences[$reference])) {
                $exceptions[] = ['class' => 'RECEIPT_NO_GATEWAY', 'gateway_reference' => $reference, 'amount_minor' => $intent->amount_minor];
            }
        }

        return $exceptions;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function bankExceptions(RunReconciliationData $data): array
    {
        if ($data->bankAccountId === null) {
            return [];
        }

        $unmatchedCredits = BankStatementLine::query()
            ->where('school_id', $data->schoolId)
            ->where('match_status', 'unmatched')
            ->where('credit_minor', '>', 0)
            ->whereHas('statement', fn ($q) => $q->where('bank_account_id', $data->bankAccountId))
            ->get();

        return $unmatchedCredits->map(fn (BankStatementLine $line): array => [
            'class' => 'BANK_NO_RECEIPT',
            'bank_statement_line_id' => $line->id,
            'amount_minor' => $line->credit_minor,
            'description' => $line->description,
        ])->all();
    }
}

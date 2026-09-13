<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\SetPostingRuleData;
use Modules\Finance\Models\PostingRule;

/**
 * ACT-SetPostingRule (Book B FIN-01 §4/§8 `Finance\PostingRules\Index`).
 * Not named in §5's own domain action table — that table lists the
 * engine's own read path (posting_rules is consulted, never written, by
 * PostJournalAction/JournalAssembler, since a posting rule affects
 * FUTURE billing/receipting events, not the ledger itself) but the
 * spec's own screen list names a real `finance.posting_rule.manage`
 * screen with nothing to back it. Upserts by (school_id, event_key),
 * the same natural key `posting_rules`' own unique index uses.
 */
final class SetPostingRuleAction extends Action
{
    public function execute(SetPostingRuleData $data): PostingRule
    {
        return $this->transaction(fn (): PostingRule => PostingRule::updateOrCreate(
            ['school_id' => $data->schoolId, 'event_key' => $data->eventKey],
            [
                'debit_account_id' => $data->debitAccountId,
                'credit_account_id' => $data->creditAccountId,
                'debit_resolver' => $data->debitResolver,
                'credit_resolver' => $data->creditResolver,
                'cost_centre_id' => $data->costCentreId,
                'is_active' => $data->isActive,
            ],
        ));
    }
}

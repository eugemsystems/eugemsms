<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\SafeguardingConcern;

/**
 * ACT-FindConcernByAnonymousToken (Book G BRD-08 §4 — "the learner can
 * return with their token to see a response"). The token is the ONLY
 * route back to the report — this is a direct token lookup, never a
 * search by any other field.
 */
final class FindConcernByAnonymousTokenAction extends Action
{
    public function execute(string $token): ?SafeguardingConcern
    {
        return SafeguardingConcern::query()->where('anonymous_token', $token)->first();
    }
}

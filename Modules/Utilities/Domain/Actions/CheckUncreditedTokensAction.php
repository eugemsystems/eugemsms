<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Utilities\Domain\Events\TokenUncredited;
use Modules\Utilities\Models\PrepaidTokenPurchase;

/**
 * ACT-CheckUncreditedTokens (Book H2 OPS-04 §3/BR-OPS-04-002/
 * AC-OPS-04-001). Nightly reconciliation's first half — a token still
 * `purchased` past the configured window is money nobody has
 * confirmed actually reached the meter.
 */
final class CheckUncreditedTokensAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return Collection<int, PrepaidTokenPurchase>
     */
    public function execute(int $schoolId): Collection
    {
        $windowHours = (int) $this->settings->get('utilities.token_credit_window_hours', new ScopeChain(schoolId: $schoolId));
        $cutoff = Carbon::now()->subHours($windowHours);

        $uncredited = PrepaidTokenPurchase::where('school_id', $schoolId)
            ->where('status', 'purchased')
            ->where('purchased_at', '<=', $cutoff)
            ->get();

        foreach ($uncredited as $purchase) {
            $hoursUncredited = (int) $purchase->purchased_at->diffInHours(Carbon::now());
            event(new TokenUncredited($purchase, $hoursUncredited));
        }

        return $uncredited;
    }
}

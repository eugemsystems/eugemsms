<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Actions;

use App\Models\User;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Auth\UserType;
use Modules\Saas\Models\ChurnRiskFlag;

/**
 * ACT-ReviewChurnRiskFlag (Book J SAA-03 §2/BR-SAA-03-008). A person moves
 * a flag `open → intervention_logged → resolved | churned`, optionally
 * assigning it to vendor staff; a resolved or churned flag is final. Churn
 * scores are advisory — nothing here contacts the customer.
 */
final class ReviewChurnRiskFlagAction extends Action
{
    /** @var array<string, array<int, string>> */
    private const array TRANSITIONS = [
        'open' => ['intervention_logged', 'resolved', 'churned'],
        'intervention_logged' => ['resolved', 'churned'],
        'resolved' => [],
        'churned' => [],
    ];

    public function execute(int $flagId, ?string $status = null, ?int $assignToVendorUserId = null): ChurnRiskFlag
    {
        $flag = ChurnRiskFlag::query()->findOrFail($flagId);

        if ($status !== null && ! in_array($status, self::TRANSITIONS[$flag->status] ?? [], true)) {
            throw new InvalidArgumentException("A {$flag->status} flag cannot move to {$status}.");
        }

        if ($assignToVendorUserId !== null && ! User::query()->where('user_type', UserType::Vendor)->whereKey($assignToVendorUserId)->exists()) {
            throw new InvalidArgumentException('A flag can only be assigned to vendor staff.');
        }

        return $this->transaction(function () use ($flag, $status, $assignToVendorUserId): ChurnRiskFlag {
            $flag->update(array_filter(['status' => $status, 'assigned_to' => $assignToVendorUserId], fn (mixed $value): bool => $value !== null));

            return $flag->fresh();
        });
    }
}

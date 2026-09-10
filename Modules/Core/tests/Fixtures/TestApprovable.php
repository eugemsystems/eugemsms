<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Core\Domain\Contracts\Approvals\Approvable;
use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\House;

/**
 * A minimal `Approvable` for CORE-07's tests — backed by the real
 * `houses` table (via `House`) purely so it's a genuine persisted
 * Eloquent model with an id, without a dedicated fixture migration.
 * Nothing about "houses" is meaningful here.
 */
class TestApprovable extends House implements Approvable
{
    protected $table = 'houses';

    public ?Money $testAmount = null;

    public static int $approvedCount = 0;

    public static int $rejectedCount = 0;

    public static int $returnedCount = 0;

    public static function resetCounters(): void
    {
        self::$approvedCount = 0;
        self::$rejectedCount = 0;
        self::$returnedCount = 0;
    }

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('code'))) {
                $model->setAttribute('code', Str::upper(Str::random(6)));
            }
        });
    }

    public function approvableType(): string
    {
        return 'test_approvable';
    }

    public function approvalTitle(): string
    {
        return "Test approvable #{$this->id}";
    }

    public function approvalSummary(): ?string
    {
        return $this->motto;
    }

    public function approvalAmount(): ?Money
    {
        return $this->testAmount;
    }

    public function approvalPayload(): array
    {
        return [
            'amount_minor' => $this->testAmount?->minor,
            'name' => $this->name,
        ];
    }

    public function onApproved(ApprovalRequest $request): void
    {
        self::$approvedCount++;
    }

    public function onRejected(ApprovalRequest $request): void
    {
        self::$rejectedCount++;
    }

    public function onReturned(ApprovalRequest $request): void
    {
        self::$returnedCount++;
    }
}

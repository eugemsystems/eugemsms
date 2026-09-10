<?php

declare(strict_types=1);

namespace Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Finance\Models\Account;
use Modules\People\Models\Guardian;
use Modules\People\Models\Student;
use Modules\Wallet\Database\Factories\StudentWalletFactory;

/**
 * Book H3 FIN-14 §2/§3 ⭐/BR-FIN-14-001. `balance_minor` is a cache —
 * `WalletTransaction` (append-only) is the source of truth.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $student_id
 * @property int $balance_minor
 * @property string $currency
 * @property int $liability_account_id
 * @property string $status
 * @property int|null $daily_limit_minor
 * @property int|null $weekly_limit_minor
 * @property int|null $per_transaction_limit_minor
 * @property array<int, string>|null $blocked_categories
 * @property int|null $low_balance_threshold_minor
 * @property bool $auto_topup_enabled
 * @property int|null $auto_topup_amount_minor
 * @property int|null $controls_set_by
 * @property Carbon|null $controls_updated_at
 * @property Carbon|null $last_transaction_at
 */
class StudentWallet extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentWalletFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'student_id', 'balance_minor', 'currency', 'liability_account_id', 'status',
        'daily_limit_minor', 'weekly_limit_minor', 'per_transaction_limit_minor', 'blocked_categories',
        'low_balance_threshold_minor', 'auto_topup_enabled', 'auto_topup_amount_minor', 'controls_set_by',
        'controls_updated_at', 'last_transaction_at',
    ];

    protected function casts(): array
    {
        return [
            'blocked_categories' => 'array',
            'auto_topup_enabled' => 'boolean',
            'controls_updated_at' => 'datetime',
            'last_transaction_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StudentWalletFactory::new();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function controlsSetBy(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'controls_set_by');
    }
}

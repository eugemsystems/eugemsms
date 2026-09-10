<?php

declare(strict_types=1);

namespace Modules\Payroll\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Payroll\Database\Factories\StatutoryConfigurationFactory;

/**
 * Book H3 PPL-05 §2/§0.1 ⭐. `school_id` null means "system default" —
 * deliberately NOT `BelongsToSchool` (see the migration's docblock).
 * Immutable once created except for the confirmation columns and
 * `status` (active → superseded, when a later-effective row
 * supersedes this one) — BR-PPL-05-004: superseding never rewrites a
 * historical row.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $school_id
 * @property string $config_type
 * @property string|null $currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property array<string, mixed> $configuration
 * @property string|null $source_reference
 * @property bool $requires_confirmation
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property string $status
 */
class StatutoryConfiguration extends Model
{
    /** @use HasFactory<StatutoryConfigurationFactory> */
    use HasFactory;

    use HasUlid;

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    private const array MUTABLE_AFTER_CREATE = ['confirmed_by', 'confirmed_at', 'status'];

    protected $fillable = [
        'school_id', 'config_type', 'currency', 'effective_from', 'effective_to', 'configuration',
        'source_reference', 'requires_confirmation', 'confirmed_by', 'confirmed_at', 'status',
        'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'configuration' => 'array',
            'requires_confirmation' => 'boolean',
            'confirmed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return StatutoryConfigurationFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Model $model): void {
            $dirty = array_keys($model->getDirty());
            $notAllowed = array_diff($dirty, self::MUTABLE_AFTER_CREATE);

            if ($notAllowed !== []) {
                throw new InvalidStateTransitionException(
                    'A statutory configuration is immutable once created, except for confirmation and status (BR-PPL-05-004).',
                    ['dirty' => $notAllowed],
                );
            }
        });
    }

    public function isConfirmed(): bool
    {
        return ! $this->requires_confirmation || $this->confirmed_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}

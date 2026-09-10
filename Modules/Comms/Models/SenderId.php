<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\SenderIdFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-01 §2/BR-COM-01-008.
 *
 * @property int $id
 * @property int $school_id
 * @property int $gateway_id
 * @property string $sender_id
 * @property string|null $network
 * @property string|null $registration_reference
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $expires_on
 * @property string|null $rejection_reason
 */
class SenderId extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SenderIdFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'gateway_id', 'sender_id', 'network', 'registration_reference', 'status',
        'submitted_at', 'approved_at', 'expires_on', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'expires_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return SenderIdFactory::new();
    }

    /**
     * @return BelongsTo<MessageGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(MessageGateway::class, 'gateway_id');
    }

    public function isUsable(): bool
    {
        return $this->status === 'approved' && ($this->expires_on === null || $this->expires_on->isFuture());
    }
}

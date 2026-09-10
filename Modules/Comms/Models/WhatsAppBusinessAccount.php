<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\WhatsAppBusinessAccountFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book I COM-01 §2/BR-COM-01-007 ⭐.
 *
 * @property int $id
 * @property int $school_id
 * @property int $gateway_id
 * @property string $waba_id
 * @property string $display_phone_number
 * @property string $display_name
 * @property string|null $display_name_status
 * @property string|null $quality_rating
 * @property string|null $messaging_limit_tier
 * @property Carbon|null $verified_at
 * @property string $status
 */
class WhatsAppBusinessAccount extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WhatsAppBusinessAccountFactory> */
    use HasFactory;

    protected $table = 'whatsapp_business_accounts';

    protected $fillable = [
        'school_id', 'gateway_id', 'waba_id', 'display_phone_number', 'display_name',
        'display_name_status', 'quality_rating', 'messaging_limit_tier', 'verified_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WhatsAppBusinessAccountFactory::new();
    }

    /**
     * @return BelongsTo<MessageGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(MessageGateway::class, 'gateway_id');
    }

    /**
     * BR-COM-01-007: the fixed `red` case, useful for display. The
     * actual send-time gate compares against the configurable
     * `comms.whatsapp_quality_pause_threshold` setting, not this.
     */
    public function isQualityPaused(): bool
    {
        return $this->quality_rating === 'red';
    }
}

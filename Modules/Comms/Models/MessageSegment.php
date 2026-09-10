<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Comms\Database\Factories\MessageSegmentFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Models\Notification;

/**
 * Book I COM-01 §2/§4 ⭐/BR-COM-01-009.
 *
 * @property int $id
 * @property int $school_id
 * @property int $notification_id
 * @property string $encoding
 * @property int $character_count
 * @property int $segment_count
 * @property int|null $rate_card_id
 * @property int|null $cost_minor
 * @property string|null $currency
 */
class MessageSegment extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MessageSegmentFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'notification_id', 'encoding', 'character_count', 'segment_count',
        'rate_card_id', 'cost_minor', 'currency',
    ];

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MessageSegmentFactory::new();
    }

    /**
     * @return BelongsTo<Notification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * @return BelongsTo<ProviderRateCard, $this>
     */
    public function rateCard(): BelongsTo
    {
        return $this->belongsTo(ProviderRateCard::class, 'rate_card_id');
    }

    /**
     * A message that hit UCS-2 (70 chars/segment) when it could have
     * stayed GSM-7 (160 chars/segment) at 1 segment, per §4's own
     * "segment waste" report.
     */
    public function isWasted(): bool
    {
        return $this->encoding === 'ucs2' && $this->segment_count > 1 && $this->character_count <= 160;
    }
}

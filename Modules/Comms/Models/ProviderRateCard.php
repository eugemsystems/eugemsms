<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\ProviderRateCardFactory;

/**
 * Book I COM-01 §2/§4. `school_id` nullable — null rows are the
 * system default, used by any school without its own configured
 * card. Deliberately NOT `BelongsToSchool`: a null-school row must
 * remain visible to every school's own rate lookup.
 *
 * @property int $id
 * @property int|null $school_id
 * @property int $gateway_id
 * @property string $destination_prefix
 * @property int $rate_per_segment_minor
 * @property int|null $whatsapp_utility_rate_minor
 * @property int|null $whatsapp_marketing_rate_minor
 * @property string $currency
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class ProviderRateCard extends Model
{
    /** @use HasFactory<ProviderRateCardFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'gateway_id', 'destination_prefix', 'rate_per_segment_minor',
        'whatsapp_utility_rate_minor', 'whatsapp_marketing_rate_minor', 'currency',
        'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ProviderRateCardFactory::new();
    }

    /**
     * @return BelongsTo<MessageGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(MessageGateway::class, 'gateway_id');
    }
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Academic\Database\Factories\VenueFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book E ACA-03 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $venue_type
 * @property string|null $building
 * @property string|null $floor
 * @property int $capacity
 * @property int|null $exam_capacity
 * @property array<int, string>|null $facilities
 * @property bool $is_bookable_externally
 * @property bool $is_active
 */
class Venue extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'code', 'name', 'venue_type', 'building', 'floor', 'capacity',
        'exam_capacity', 'facilities', 'is_bookable_externally', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
            'is_bookable_externally' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return VenueFactory::new();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Security\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Security\Database\Factories\KeyAndCardFactory;

/**
 * Book H2 OPS-06 §2/BR-OPS-06-006.
 *
 * @property int $id
 * @property int $school_id
 * @property string $identifier
 * @property string $item_type
 * @property string $description
 * @property string|null $opens_location
 * @property bool $is_master
 * @property string $status
 */
class KeyAndCard extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<KeyAndCardFactory> */
    use HasFactory;

    protected $table = 'keys_and_cards';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'identifier', 'item_type', 'description', 'opens_location', 'is_master', 'status',
    ];

    protected function casts(): array
    {
        return [
            'is_master' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return KeyAndCardFactory::new();
    }

    /**
     * @return HasMany<KeyIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(KeyIssue::class, 'key_id');
    }
}

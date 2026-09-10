<?php

declare(strict_types=1);

namespace Modules\Boarding\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Boarding\Database\Factories\MovementCheckpointFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book F BRD-02 §2/BR-BRD-02-017.
 *
 * @property int $id
 * @property int $school_id
 * @property string $code
 * @property string $name
 * @property string $checkpoint_type
 * @property string|null $hardware_device_id
 * @property bool $is_boundary
 * @property bool $is_active
 */
class MovementCheckpoint extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MovementCheckpointFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['school_id', 'code', 'name', 'checkpoint_type', 'hardware_device_id', 'is_boundary', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_boundary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return MovementCheckpointFactory::new();
    }
}

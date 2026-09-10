<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\ThirdPartyProcessorFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book H3 CMP-03 §2/BR-CMP-03-014.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $processor_type
 * @property array<int, string> $data_shared
 * @property string $purpose
 * @property string|null $country
 * @property int|null $agreement_file_id
 * @property Carbon|null $agreement_expires_on
 * @property string $status
 * @property Carbon|null $last_reviewed_on
 */
class ThirdPartyProcessor extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ThirdPartyProcessorFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'name', 'processor_type', 'data_shared', 'purpose', 'country',
        'agreement_file_id', 'agreement_expires_on', 'status', 'last_reviewed_on',
    ];

    protected function casts(): array
    {
        return [
            'data_shared' => 'array',
            'agreement_expires_on' => 'date',
            'last_reviewed_on' => 'date',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return ThirdPartyProcessorFactory::new();
    }

    public function isCrossBorder(): bool
    {
        return $this->country !== null && $this->country !== 'ZW';
    }
}

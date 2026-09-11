<?php

declare(strict_types=1);

namespace Modules\Saas\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Concerns\Auditable;
use Modules\Core\Domain\Concerns\HasUlid;
use Modules\Saas\Database\Factories\IncidentStatusEntryFactory;

/**
 * Book J SAA-02 §2/BR-SAA-02-006/007. The public status page's own
 * backing data.
 *
 * @property int $id
 * @property string $ulid
 * @property string $title
 * @property array<int, string> $affected_components
 * @property string $severity
 * @property string $status
 * @property array<int, array{at: string, message: string, status: string}> $updates
 * @property bool $is_public
 */
class IncidentStatusEntry extends Model
{
    use Auditable;

    /** @use HasFactory<IncidentStatusEntryFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'title', 'affected_components', 'severity', 'status', 'updates', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'affected_components' => 'array',
            'updates' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return IncidentStatusEntryFactory::new();
    }
}

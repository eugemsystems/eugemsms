<?php

declare(strict_types=1);

namespace Modules\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Compliance\Database\Factories\PrivacyNoticeFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;

/**
 * Book H3 CMP-03 §2 (added — see the module migration's own docblock).
 *
 * @property int $id
 * @property int $school_id
 * @property string $version
 * @property string $title
 * @property string $content
 * @property Carbon $effective_from
 * @property bool $requires_reconsent
 * @property int $created_by
 */
class PrivacyNotice extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<PrivacyNoticeFactory> */
    use HasFactory;

    protected $fillable = [
        'school_id', 'version', 'title', 'content', 'effective_from', 'requires_reconsent', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'requires_reconsent' => 'boolean',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return PrivacyNoticeFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

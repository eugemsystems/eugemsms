<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\NewsletterFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-06 §2.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property string $issue_number
 * @property string $title
 * @property string $content_html
 * @property string $audience_scope
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $sent_at
 * @property int|null $archive_file_id
 * @property string $status
 */
class Newsletter extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<NewsletterFactory> */
    use HasFactory;

    use HasUlid;

    protected $fillable = [
        'school_id', 'issue_number', 'title', 'content_html', 'audience_scope',
        'scheduled_for', 'sent_at', 'archive_file_id', 'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return NewsletterFactory::new();
    }
}

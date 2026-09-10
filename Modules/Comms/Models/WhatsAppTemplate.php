<?php

declare(strict_types=1);

namespace Modules\Comms\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Comms\Database\Factories\WhatsAppTemplateFactory;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Concerns\HasUlid;

/**
 * Book I COM-01 §2/BR-COM-01-005/006 ⭐. See the migration's own
 * docblock for why `notification_key` is not what template selection
 * matches on.
 *
 * @property int $id
 * @property string $ulid
 * @property int $school_id
 * @property int $waba_id
 * @property string|null $notification_key
 * @property string $meta_template_name
 * @property string $category
 * @property string $language
 * @property string|null $header_type
 * @property string $body_text
 * @property string|null $footer_text
 * @property array<int, mixed>|null $buttons
 * @property Carbon|null $submitted_at
 * @property string $review_status
 * @property string|null $rejection_reason
 * @property Carbon|null $approved_at
 * @property string|null $meta_template_id
 */
class WhatsAppTemplate extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<WhatsAppTemplateFactory> */
    use HasFactory;

    use HasUlid;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'school_id', 'waba_id', 'notification_key', 'meta_template_name', 'category', 'language',
        'header_type', 'body_text', 'footer_text', 'buttons', 'submitted_at', 'review_status',
        'rejection_reason', 'approved_at', 'meta_template_id',
    ];

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return WhatsAppTemplateFactory::new();
    }

    /**
     * @return BelongsTo<WhatsAppBusinessAccount, $this>
     */
    public function whatsAppBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBusinessAccount::class, 'waba_id');
    }

    public function isApproved(): bool
    {
        return $this->review_status === 'approved';
    }

    /**
     * The template's single `{{1}}`-style placeholder filled with the
     * already-rendered CORE-09 body text.
     */
    public function fillWith(string $body): string
    {
        return str_replace('{{1}}', $body, $this->body_text);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Fiscal\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Concerns\BelongsToSchool;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Fiscal\Database\Factories\FiscalAuditLogEntryFactory;

/**
 * Book H3 FIN-13 §3/BR-FIN-13-012 — append-only, same guard shape as
 * `Modules\Security`'s `OccurrenceBookEntry` (Book H2 OPS-06).
 *
 * @property int $id
 * @property int $school_id
 * @property int|null $device_id
 * @property string $event_type
 * @property string|null $reference
 * @property array<string, mixed>|null $request_payload
 * @property array<string, mixed>|null $response_payload
 * @property int|null $http_status
 * @property int|null $duration_ms
 * @property Carbon $occurred_at
 */
class FiscalAuditLogEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<FiscalAuditLogEntryFactory> */
    use HasFactory;

    protected $table = 'fiscal_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'school_id', 'device_id', 'event_type', 'reference', 'request_payload', 'response_payload',
        'http_status', 'duration_ms', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return Factory<self>
     */
    protected static function newFactory(): Factory
    {
        return FiscalAuditLogEntryFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new InvalidStateTransitionException('fiscal_audit_log is append-only — a request/response record is never edited.');
        });

        static::deleting(function (): void {
            throw new InvalidStateTransitionException('fiscal_audit_log is append-only — a request/response record is never deleted.');
        });
    }
}

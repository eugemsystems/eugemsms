<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Core\Domain\DataObjects\Audit\RecordActivityData;
use Modules\Core\Domain\Support\SessionContext;
use Modules\Core\Jobs\LogActivityJob;

/**
 * Book A CORE-08 BR-CORE-08-001..004. Any model that `use`s this logs
 * create/update/delete/restore with a before/after diff, queued
 * (BR-CORE-08-014) so it never adds latency to the request. A model
 * lists field names it never wants written — not even as a hash — in
 * a public `array $auditExcluded` property (passwords, tokens,
 * secrets: BR-CORE-08-002).
 *
 * @mixin Model
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn (Model $model) => self::recordAuditEvent($model, 'created', [], $model->getAttributes()));

        static::updated(function (Model $model): void {
            $changes = $model->getChanges();
            unset($changes[$model->getUpdatedAtColumn()]);

            if ($changes !== []) {
                self::recordAuditEvent($model, 'updated', Arr::only($model->getOriginal(), array_keys($changes)), $changes);
            }
        });

        static::deleted(fn (Model $model) => self::recordAuditEvent($model, 'deleted', $model->getOriginal(), []));

        // `restored` has no public `static::restored()` convenience
        // registrar the way `created`/`updated`/`deleted` do (only
        // `SoftDeletes::bootSoftDeletes()` itself calls the protected
        // `registerModelEvent` for it) — registering it the same way
        // here is harmless for a model without SoftDeletes, since the
        // event simply never fires otherwise.
        static::registerModelEvent('restored', fn (Model $model) => self::recordAuditEvent($model, 'restored', [], $model->getAttributes()));
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $attributes
     */
    private static function recordAuditEvent(Model $model, string $event, array $old, array $attributes): void
    {
        $excluded = property_exists($model, 'auditExcluded') ? $model->auditExcluded : [];

        $request = function_exists('request') ? request() : null;

        LogActivityJob::dispatch(new RecordActivityData(
            logName: property_exists($model, 'auditLogName') ? $model->auditLogName : Str::snake(class_basename($model)),
            description: "{$event} ".Str::snake(class_basename($model)),
            schoolId: $model->getAttribute('school_id'),
            subjectType: $model->getMorphClass(),
            subjectId: (int) $model->getKey(),
            causerType: Auth::user()?->getMorphClass(),
            causerId: Auth::id() !== null ? (int) Auth::id() : null,
            event: $event,
            properties: [
                'old' => Arr::except($old, $excluded),
                'attributes' => Arr::except($attributes, $excluded),
            ],
            ip: $request?->ip(),
            userAgent: $request?->userAgent(),
            requestId: $request?->attributes->get('request_id'),
            academicYearId: SessionContext::isSet() ? SessionContext::yearId() : null,
            termId: SessionContext::isSet() ? SessionContext::termId() : null,
        ));
    }
}

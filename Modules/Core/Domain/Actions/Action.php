<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions;

use Closure;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Exceptions\ImpersonationReadOnlyException;
use Modules\Core\Domain\Support\Auth\ImpersonationGuard;
use Modules\Core\Domain\Support\ImpersonationContext;

/**
 * Every business operation in sERP is a single-method class extending this
 * base (Book A Part 1.1 / Volume 1 ADR-002 — "one brain, two mouths").
 * Livewire components and API controllers are thin adapters: validate
 * input, call exactly one Action, format the result. Neither may write to
 * the database directly — see BR-GLOBAL-005 and the CI rule that enforces
 * it (tests/Feature/Architecture/ActionPatternEnforcementTest.php).
 *
 * Rules an Action must honour (BR-GLOBAL-001 .. 006):
 *  - accepts exactly one readonly DTO, returns exactly one typed result or void;
 *  - re-checks authorisation itself, never trusting the caller;
 *  - never reads request(), session(), or auth() directly — everything it
 *    needs arrives on the DTO;
 *  - never returns an HTTP response, a Blade view, or an API Resource;
 *  - has a unit test that calls it directly, without HTTP and without Livewire.
 */
abstract class Action
{
    /**
     * Every action executes inside a database transaction by default.
     * Override to false only for actions that manage their own transaction
     * boundaries (batch runs, imports).
     */
    protected bool $transactional = true;

    /**
     * Wrap a unit of work in the action's transaction boundary. Concrete
     * actions call this from inside execute() around their mutation:
     *
     *   public function execute(FooData $data): FooResult
     *   {
     *       // 1. authorise, 2. validate
     *       return $this->transaction(function () use ($data) {
     *           // 3. mutate, 4. dispatch events
     *           return new FooResult(...);
     *       });
     *   }
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function transaction(Closure $callback): mixed
    {
        $this->assertWritableDuringImpersonation();

        if (! $this->transactional) {
            return $callback();
        }

        return DB::transaction($callback);
    }

    /**
     * Set to true only on an Action that must keep working inside a read-only support session —
     * ending that session, or switching the school/session being looked at. Anything that records
     * or changes business data must stay false.
     */
    protected bool $allowedDuringReadOnlyImpersonation = false;

    /**
     * Book J SAA-02 BR-SAA-02-002: a vendor support session is read-only. Every business write
     * goes through an Action, so refusing here is the one choke point that holds for every module
     * without each remembering to ask — a support engineer can see what the customer sees and
     * change nothing, and cannot mark a notice "read" or approve a request as the customer.
     */
    private function assertWritableDuringImpersonation(): void
    {
        $session = ImpersonationContext::current();

        if ($session === null || ! $session->is_read_only || ! $session->isActive() || $this->allowedDuringReadOnlyImpersonation) {
            return;
        }

        $session->recordBlockedAction('Write attempted by '.static::class);

        throw new ImpersonationReadOnlyException('This is a read-only support session — nothing can be changed while it is open.', ['action' => class_basename(static::class)]);
    }

    /**
     * BR-CORE-05-018. Call from the top of `execute()` on any action that
     * is a financial mutation or a bulk export — `ImpersonationGuard`'s own
     * three categories (`FINANCIAL_MUTATION`, `PERMISSION_CHANGE`,
     * `BULK_EXPORT`). `ImpersonationContext` is a request-scoped context
     * singleton, not `session()`/`request()`/`auth()` directly — the same
     * sanctioned indirection `SchoolContext`/`SessionContext` already use
     * from inside `SwitchActiveSchoolAction`/`SwitchSessionAction`, set by
     * `SetImpersonationContext` middleware (applied globally, no route
     * needs to remember to ask for it). Only ever `null` (no active
     * impersonation) for the overwhelming majority of calls, in which case
     * this is a no-op.
     */
    protected function assertNotImpersonating(string $category, string $description): void
    {
        app(ImpersonationGuard::class)->assertPermitted(ImpersonationContext::current(), $category, $description);
    }
}

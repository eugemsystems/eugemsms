<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Sessions\ExecuteRolloverAction;
use Modules\Core\Domain\Actions\Sessions\InitiateRolloverAction;
use Modules\Core\Domain\Contracts\Sessions\RolloverHandler;
use Modules\Core\Domain\DataObjects\Sessions\ExecuteRolloverData;
use Modules\Core\Domain\DataObjects\Sessions\HandlerResult;
use Modules\Core\Domain\DataObjects\Sessions\InitiateRolloverData;
use Modules\Core\Domain\DataObjects\Sessions\RolloverContext;
use Modules\Core\Domain\DataObjects\Sessions\ValidationResult;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Registry\RolloverHandlerRegistry;
use Modules\Core\Domain\Support\RolloverStatus;
use Modules\Core\Models\PeriodRollover;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;

/**
 * These tests replace the registry wholesale so they exercise exactly
 * the handler pipeline they set up — CORE-03's own registered handlers
 * (snapshot/invariant) stay registered too, but the pending/real-module
 * handlers are cleared so only the test doubles below run.
 */
function withRolloverHandlers(array $handlers, Closure $callback): mixed
{
    $original = RolloverHandlerRegistry::all();
    RolloverHandlerRegistry::clear();

    foreach ($handlers as $handler) {
        RolloverHandlerRegistry::register($handler);
    }

    try {
        return $callback();
    } finally {
        RolloverHandlerRegistry::clear();

        foreach ($original as $handler) {
            RolloverHandlerRegistry::register($handler);
        }
    }
}

function fakeHandler(string $module, int $order, bool $blocking, bool $succeeds, ?Closure $onExecute = null, ?Closure $onRollback = null): RolloverHandler
{
    return new class($module, $order, $blocking, $succeeds, $onExecute, $onRollback) implements RolloverHandler
    {
        public function __construct(
            private string $module,
            private int $order,
            private bool $blocking,
            private bool $succeeds,
            private ?Closure $onExecute,
            private ?Closure $onRollback,
        ) {}

        public function moduleCode(): string
        {
            return $this->module;
        }

        public function sortOrder(): int
        {
            return $this->order;
        }

        public function isBlocking(): bool
        {
            return $this->blocking;
        }

        public function validate(Term $from, Term $to): ValidationResult
        {
            return ValidationResult::pass();
        }

        public function execute(Term $from, Term $to, RolloverContext $context): HandlerResult
        {
            if ($this->onExecute !== null) {
                ($this->onExecute)($context);
            }

            return new HandlerResult($this->succeeds, $this->succeeds ? 'ok' : 'failed');
        }

        public function rollback(Term $from, Term $to, RolloverContext $context): void
        {
            if ($this->onRollback !== null) {
                ($this->onRollback)($context);
            }
        }
    };
}

it('cannot initiate a second roll-over while one is already in progress (BR-CORE-03-022/AC-CORE-03-009)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $termA = Term::factory()->for($school)->create(['number' => 1]);
    $termB = Term::factory()->for($school)->create(['number' => 2]);
    $termC = Term::factory()->for($school)->create(['number' => 3]);

    PeriodRollover::factory()->for($school)->create([
        'from_term_id' => $termA->id,
        'to_term_id' => $termB->id,
        'status' => RolloverStatus::Running,
    ]);

    app(InitiateRolloverAction::class)->execute(new InitiateRolloverData($school->id, $termB->id, $termC->id, $user->id));
})->throws(InvalidStateTransitionException::class);

it('rolls back every already-executed handler and leaves the rollover marked failed when a blocking handler fails (BR-CORE-03-017)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $fromTerm = Term::factory()->for($school)->create(['number' => 1]);
    $toTerm = Term::factory()->for($school)->create(['number' => 2]);

    $rollback1Called = false;
    $rollback2Called = false;

    $handlers = [
        fakeHandler('TEST-1', 10, true, true, onRollback: function () use (&$rollback1Called): void {
            $rollback1Called = true;
        }),
        fakeHandler('TEST-2', 20, true, true, onRollback: function () use (&$rollback2Called): void {
            $rollback2Called = true;
        }),
        fakeHandler('TEST-3', 30, true, false),
    ];

    withRolloverHandlers($handlers, function () use ($school, $fromTerm, $toTerm, $user): void {
        $rollover = app(InitiateRolloverAction::class)->execute(new InitiateRolloverData($school->id, $fromTerm->id, $toTerm->id, $user->id));
        expect($rollover->status)->toBe(RolloverStatus::Pending);

        $result = app(ExecuteRolloverAction::class)->execute(new ExecuteRolloverData($rollover->id, $user->id));

        expect($result->status)->toBe(RolloverStatus::Failed);
    });

    expect($rollback1Called)->toBeTrue()->and($rollback2Called)->toBeTrue();

    $rollover = PeriodRollover::withoutGlobalScopes()->where('school_id', $school->id)->sole();
    expect($rollover->status)->toBe(RolloverStatus::Failed)
        ->and($rollover->exception_report['failing_handler'])->not->toBeEmpty();
});

it('completes and records a non-blocking handler\'s failure without aborting (BR-CORE-03-023)', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $fromTerm = Term::factory()->for($school)->create(['number' => 1]);
    $toTerm = Term::factory()->for($school)->create(['number' => 2]);

    $handlers = [
        fakeHandler('TEST-1', 10, true, true),
        fakeHandler('TEST-2', 20, false, false),
    ];

    withRolloverHandlers($handlers, function () use ($school, $fromTerm, $toTerm, $user): void {
        $rollover = app(InitiateRolloverAction::class)->execute(new InitiateRolloverData($school->id, $fromTerm->id, $toTerm->id, $user->id));
        $result = app(ExecuteRolloverAction::class)->execute(new ExecuteRolloverData($rollover->id, $user->id));

        expect($result->status)->toBe(RolloverStatus::Completed);
        expect(collect($result->stepLog)->firstWhere('module', 'TEST-2')['success'])->toBeFalse();
    });
});

it('fails initiation validation when a blocking handler reports it would fail', function (): void {
    $school = School::factory()->create();
    $user = User::factory()->create();
    $fromTerm = Term::factory()->for($school)->create(['number' => 1]);
    $toTerm = Term::factory()->for($school)->create(['number' => 2]);

    $failingValidator = new class implements RolloverHandler
    {
        public function moduleCode(): string
        {
            return 'TEST-BAD';
        }

        public function sortOrder(): int
        {
            return 10;
        }

        public function isBlocking(): bool
        {
            return true;
        }

        public function validate(Term $from, Term $to): ValidationResult
        {
            return ValidationResult::fail(['Trial balance is out of balance.']);
        }

        public function execute(Term $from, Term $to, RolloverContext $context): HandlerResult
        {
            return new HandlerResult(true);
        }

        public function rollback(Term $from, Term $to, RolloverContext $context): void {}
    };

    withRolloverHandlers([$failingValidator], function () use ($school, $fromTerm, $toTerm, $user): void {
        $rollover = app(InitiateRolloverAction::class)->execute(new InitiateRolloverData($school->id, $fromTerm->id, $toTerm->id, $user->id));

        expect($rollover->status)->toBe(RolloverStatus::Failed);
    });
});

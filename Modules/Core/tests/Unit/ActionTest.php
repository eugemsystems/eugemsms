<?php

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;

it('wraps execute in a database transaction by default', function () {
    $action = new class extends Action
    {
        public function execute(): string
        {
            return $this->transaction(function (): string {
                expect(DB::transactionLevel())->toBe(1);

                return 'done';
            });
        }
    };

    expect(DB::transactionLevel())->toBe(0)
        ->and($action->execute())->toBe('done')
        ->and(DB::transactionLevel())->toBe(0);
});

it('rolls back the transaction when the callback throws', function () {
    $action = new class extends Action
    {
        public function execute(): void
        {
            $this->transaction(function (): void {
                throw new RuntimeException('boom');
            });
        }
    };

    expect(fn () => $action->execute())->toThrow(RuntimeException::class, 'boom');
    expect(DB::transactionLevel())->toBe(0);
});

it('does not open a transaction when the action manages its own boundary', function () {
    $action = new class extends Action
    {
        protected bool $transactional = false;

        public function execute(): string
        {
            return $this->transaction(function (): string {
                expect(DB::transactionLevel())->toBe(0);

                return 'done';
            });
        }
    };

    expect($action->execute())->toBe('done');
});

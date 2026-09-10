<?php

use Modules\Core\Domain\Exceptions\AuthorisationException;
use Modules\Core\Domain\Exceptions\ContextException;
use Modules\Core\Domain\Exceptions\DomainException;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Domain\Exceptions\FiscalisationFailedException;
use Modules\Core\Domain\Exceptions\GatewayUnreachableException;
use Modules\Core\Domain\Exceptions\InsufficientBalanceException;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Exceptions\IntegrationException;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Exceptions\MissingSchoolContextException;
use Modules\Core\Domain\Exceptions\MissingSessionContextException;
use Modules\Core\Domain\Exceptions\ModuleNotEnabledException;
use Modules\Core\Domain\Exceptions\PeriodLockedException;
use Modules\Core\Domain\Exceptions\SerpException;
use Modules\Core\Domain\Exceptions\UnauthorisedSchoolAccessException;

it('renders 422 for every domain exception', function (string $class) {
    expect(new $class('message'))
        ->toBeInstanceOf(DomainException::class)
        ->httpStatus()->toBe(422);
})->with([
    PeriodLockedException::class,
    InsufficientBalanceException::class,
    DuplicateRecordException::class,
    InvalidStateTransitionException::class,
]);

it('renders 403 for every authorisation exception', function (string $class) {
    expect(new $class('message'))
        ->toBeInstanceOf(AuthorisationException::class)
        ->httpStatus()->toBe(403);
})->with([
    UnauthorisedSchoolAccessException::class,
    ModuleNotEnabledException::class,
    InsufficientScopeException::class,
]);

it('renders 400 for every context exception', function (string $class) {
    expect(new $class('message'))
        ->toBeInstanceOf(ContextException::class)
        ->httpStatus()->toBe(400);
})->with([
    MissingSchoolContextException::class,
    MissingSessionContextException::class,
]);

it('renders 502 and is always retryable for every integration exception', function (string $class) {
    $exception = new $class('message');

    expect($exception)->toBeInstanceOf(IntegrationException::class)
        ->httpStatus()->toBe(502);
    expect($exception->isRetryable())->toBeTrue();
})->with([
    GatewayUnreachableException::class,
    FiscalisationFailedException::class,
]);

it('carries a stable machine code and a safe message in the error envelope', function () {
    $exception = new PeriodLockedException('This term is closed.', ['term_id' => 14]);

    expect($exception)->toBeInstanceOf(SerpException::class);

    expect($exception->toErrorEnvelope())->toBe([
        'success' => false,
        'error' => [
            'code' => 'PERIOD_LOCKED',
            'message' => 'This term is closed.',
            'details' => ['term_id' => 14],
        ],
    ]);
});

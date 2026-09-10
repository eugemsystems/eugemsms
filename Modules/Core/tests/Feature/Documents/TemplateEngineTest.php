<?php

use Modules\Core\Domain\Exceptions\TemplateSandboxViolationException;
use Modules\Core\Domain\Exceptions\UnknownTemplateVariableException;
use Modules\Core\Domain\Registry\TemplateVariableRegistry;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Documents\TemplateParser;
use Modules\Core\Domain\Support\Documents\TemplateRenderer;
use Modules\Core\Domain\Support\Documents\TemplateValidator;
use Modules\Core\Domain\Support\Money;

beforeEach(function (): void {
    TemplateVariableRegistry::clear();
});

it('renders a simple variable interpolation', function (): void {
    $renderer = new TemplateRenderer(new TemplateParser);

    expect($renderer->render('Hello {{ school.name }}', ['school' => ['name' => 'Eugem School']]))
        ->toBe('Hello Eugem School');
});

it('applies a chain of filters', function (): void {
    $renderer = new TemplateRenderer(new TemplateParser);

    expect($renderer->render('{{ learner.full_name | upper }}', ['learner' => ['full_name' => 'tendai moyo']]))
        ->toBe('TENDAI MOYO');
});

it('formats money via the money filter', function (): void {
    $renderer = new TemplateRenderer(new TemplateParser);
    $money = Money::of(150000, Currency::USD);

    expect($renderer->render('{{ invoice.balance | money }}', ['invoice' => ['balance' => $money]]))
        ->toBe($money->format());
});

it('renders an @if block only when the condition holds', function (): void {
    $renderer = new TemplateRenderer(new TemplateParser);
    $template = '@if(invoice.balance_minor > 0)Amount due@endif';

    expect($renderer->render($template, ['invoice' => ['balance_minor' => 500]]))->toBe('Amount due')
        ->and($renderer->render($template, ['invoice' => ['balance_minor' => 0]]))->toBe('');
});

it('renders an @foreach block once per item, scoping the loop variable', function (): void {
    $renderer = new TemplateRenderer(new TemplateParser);
    $template = '@foreach(results as result){{ result.subject }}:{{ result.grade }};@endforeach';

    $output = $renderer->render($template, [
        'results' => [
            ['subject' => 'Maths', 'grade' => 'A'],
            ['subject' => 'English', 'grade' => 'B'],
        ],
    ]);

    expect($output)->toBe('Maths:A;English:B;');
});

it('renders identical output for identical input, proving determinism (BR-CORE-06-011)', function (): void {
    $renderer = new TemplateRenderer(new TemplateParser);
    $template = '{{ school.name }} — {{ invoice.balance_minor | number }}';
    $data = ['school' => ['name' => 'Eugem'], 'invoice' => ['balance_minor' => 12345]];

    expect($renderer->render($template, $data))->toBe($renderer->render($template, $data));
});

it('rejects a template referencing an unregistered variable (BR-CORE-06-010/AC-CORE-06-004)', function (): void {
    TemplateVariableRegistry::register('receipt', ['school.name']);

    (new TemplateValidator(new TemplateParser))->validate('receipt', '{{ invoice.balance }}');
})->throws(UnknownTemplateVariableException::class);

it('accepts a template whose variables are all registered', function (): void {
    TemplateVariableRegistry::register('receipt', ['school.name', 'invoice.balance_minor', 'results.*']);

    (new TemplateValidator(new TemplateParser))->validate(
        'receipt',
        '{{ school.name }} {{ invoice.balance_minor | number }} @foreach(results as result){{ result.subject }}@endforeach',
    );

    expect(true)->toBeTrue();
});

it('rejects a template calling an unregistered filter (BR-CORE-06-009)', function (): void {
    TemplateVariableRegistry::register('receipt', ['school.name']);

    (new TemplateValidator(new TemplateParser))->validate('receipt', '{{ school.name | shell_exec }}');
})->throws(TemplateSandboxViolationException::class);

it('rejects any directive other than @if/@foreach as a sandbox violation (BR-CORE-06-009)', function (): void {
    (new TemplateParser)->parse('@php(exit) hi');
})->throws(TemplateSandboxViolationException::class);

it('rejects an unclosed @if block', function (): void {
    (new TemplateParser)->parse('@if(a > 0)no end');
})->throws(TemplateSandboxViolationException::class);

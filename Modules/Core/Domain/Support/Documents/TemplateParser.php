<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Documents;

use Modules\Core\Domain\Exceptions\TemplateSandboxViolationException;

/**
 * Book A CORE-06 §4/BR-CORE-06-009. Parses the sandboxed template
 * syntax into a `TemplateNode` tree. There is no `eval()`, no PHP
 * function call of any kind, and no directive beyond `@if`/`@endif`/
 * `@foreach`/`@endforeach` — anything else (`@php`, an unrecognised
 * `@directive`, unbalanced blocks) is a sandbox violation caught here,
 * at parse time, before either validation or rendering ever runs.
 */
final class TemplateParser
{
    private const string TOKEN_PATTERN = '/(\{\{\s*.+?\s*\}\}|@if\(.+?\)|@endif|@foreach\(.+?\)|@endforeach|@\w+\(.*?\))/s';

    /**
     * @return array<int, TemplateNode>
     */
    public function parse(string $content): array
    {
        $pieces = preg_split(self::TOKEN_PATTERN, $content, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        [$nodes, $remaining] = $this->parseNodes($pieces);

        if ($remaining !== []) {
            throw new TemplateSandboxViolationException('An @if or @foreach block was never closed.');
        }

        return $nodes;
    }

    /**
     * @param  array<int, string>  $tokens
     * @return array{0: array<int, TemplateNode>, 1: array<int, string>}
     */
    private function parseNodes(array $tokens, ?string $closingTag = null): array
    {
        $nodes = [];

        while ($tokens !== []) {
            $token = array_shift($tokens);

            if ($token === '') {
                continue;
            }

            if ($closingTag !== null && $token === $closingTag) {
                return [$nodes, $tokens];
            }

            if (str_starts_with($token, '{{')) {
                $nodes[] = $this->parseVariable($token);

                continue;
            }

            if (str_starts_with($token, '@if(')) {
                $condition = $this->extractArgs($token, '@if');
                [$children, $tokens] = $this->parseNodes($tokens, '@endif');
                $nodes[] = TemplateNode::ifBlock($condition, $children);

                continue;
            }

            if (str_starts_with($token, '@foreach(')) {
                $expression = $this->extractArgs($token, '@foreach');

                if (! preg_match('/^([a-zA-Z0-9_.]+)\s+as\s+([a-zA-Z0-9_]+)$/', $expression, $matches)) {
                    throw new TemplateSandboxViolationException("Malformed @foreach expression: [{$expression}].");
                }

                [$children, $tokens] = $this->parseNodes($tokens, '@endforeach');
                $nodes[] = TemplateNode::foreachBlock($matches[1], $matches[2], $children);

                continue;
            }

            if (str_starts_with($token, '@')) {
                throw new TemplateSandboxViolationException("Unrecognised template directive: [{$token}].", ['directive' => $token]);
            }

            $nodes[] = TemplateNode::text($token);
        }

        if ($closingTag !== null) {
            throw new TemplateSandboxViolationException("Missing [{$closingTag}] for a block opened earlier in the template.");
        }

        return [$nodes, []];
    }

    private function extractArgs(string $token, string $directive): string
    {
        return trim(substr($token, strlen($directive) + 1, -1));
    }

    private function parseVariable(string $token): TemplateNode
    {
        $inner = trim(substr($token, 2, -2));
        $segments = array_map('trim', explode('|', $inner));
        $path = array_shift($segments);

        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $path)) {
            throw new TemplateSandboxViolationException("Invalid variable reference: [{$path}].", ['path' => $path]);
        }

        $filters = array_map(function (string $segment): array {
            [$name, $argString] = array_pad(explode(':', $segment, 2), 2, null);

            if (! preg_match('/^[a-zA-Z0-9_]+$/', (string) $name)) {
                throw new TemplateSandboxViolationException("Invalid filter name: [{$name}].", ['filter' => $name]);
            }

            return [
                'name' => $name,
                'args' => $argString !== null
                    ? array_map(fn (string $arg): string => $this->stripQuotes(trim($arg)), explode(',', $argString))
                    : [],
            ];
        }, $segments);

        return TemplateNode::variable($path, $filters);
    }

    private function stripQuotes(string $value): string
    {
        if (strlen($value) >= 2 && (($value[0] === "'" && str_ends_with($value, "'")) || ($value[0] === '"' && str_ends_with($value, '"')))) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

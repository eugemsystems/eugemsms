<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Notifications;

use Modules\Core\Domain\Exceptions\MissingNotificationVariableException;
use Modules\Core\Domain\Support\Documents\TemplateNode;
use Modules\Core\Domain\Support\Documents\TemplateParser;
use Modules\Core\Domain\Support\Documents\TemplateRenderer;

/**
 * Book A CORE-09 BR-CORE-09-003/AC-CORE-09-002. Reuses CORE-06's
 * template parser/renderer for the `{{ path }}` syntax rather than a
 * second implementation — the one difference notifications need is
 * this class's own job: fail loudly the moment a referenced variable
 * is missing from the render context, before a single character is
 * sent, rather than CORE-06's save-time "is this variable registered
 * at all" check.
 */
final class NotificationRenderer
{
    public function __construct(
        private readonly TemplateParser $parser,
        private readonly TemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function render(string $body, array $context): string
    {
        $this->assertAllVariablesResolvable($this->parser->parse($body), $context, []);

        return $this->renderer->render($body, $context);
    }

    /**
     * @param  array<int, TemplateNode>  $nodes
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $aliases
     */
    private function assertAllVariablesResolvable(array $nodes, array $context, array $aliases): void
    {
        foreach ($nodes as $node) {
            if ($node->type === 'variable') {
                $this->assertResolvable((string) $node->path, $context, $aliases);
            }

            if ($node->type === 'if' || $node->type === 'foreach') {
                $this->assertAllVariablesResolvable($node->children, $context, $aliases);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, string>  $aliases
     */
    private function assertResolvable(string $path, array $context, array $aliases): void
    {
        [$root, $rest] = array_pad(explode('.', $path, 2), 2, null);
        $resolvedPath = $root !== null && array_key_exists($root, $aliases)
            ? ($rest !== null ? "{$aliases[$root]}.{$rest}" : $aliases[$root])
            : $path;

        $value = $context;

        foreach (explode('.', $resolvedPath) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];

                continue;
            }

            throw new MissingNotificationVariableException(
                "The rendering context has no value for [{$path}].",
                ['variable' => $path],
            );
        }
    }
}

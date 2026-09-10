<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Closure;
use Modules\Comms\Domain\DataObjects\DeepLinkResult;
use Modules\Comms\Domain\Events\DeepLinkResolved;
use Modules\Comms\Domain\Registry\DeepLinkRegistry;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ResolveDeepLink (Book I COM-03 §6/BR-COM-03-011). An unregistered
 * link type or a permission refusal both degrade to the nearest
 * permitted PARENT screen — never a blank error.
 */
final class ResolveDeepLinkAction extends Action
{
    protected bool $transactional = false;

    /**
     * @param  Closure(string, int): bool  $canView
     */
    public function execute(string $linkType, int $linkId, Closure $canView): DeepLinkResult
    {
        $link = DeepLinkRegistry::get($linkType);

        if ($link === null) {
            event(new DeepLinkResolved($linkType, $linkId, degraded: true));

            return new DeepLinkResult('/', degraded: true);
        }

        if (! $canView($linkType, $linkId)) {
            event(new DeepLinkResolved($linkType, $linkId, degraded: true));

            return new DeepLinkResult($link['fallback'], degraded: true);
        }

        return new DeepLinkResult(str_replace('{id}', (string) $linkId, $link['path']), degraded: false);
    }
}

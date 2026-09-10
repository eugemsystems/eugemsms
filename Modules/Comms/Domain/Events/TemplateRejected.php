<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\WhatsAppTemplate;

final class TemplateRejected
{
    public function __construct(
        public readonly WhatsAppTemplate $template,
    ) {}
}

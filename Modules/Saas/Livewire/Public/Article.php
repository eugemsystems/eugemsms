<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Public;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Modules\Saas\Models\KnowledgeBaseArticle;

/**
 * One public help article. Its Markdown is rendered with raw HTML escaped
 * and unsafe links refused, so article content can never inject script.
 */
#[Layout('saas::layouts.public')]
final class Article extends Component
{
    public string $slug = '';

    public function mount(string $slug): void
    {
        $this->slug = KnowledgeBaseArticle::query()->where('slug', $slug)->firstOrFail()->slug;
    }

    public function render(): View
    {
        return view('saas::public.article', ['article' => KnowledgeBaseArticle::query()->where('slug', $this->slug)->firstOrFail()]);
    }
}

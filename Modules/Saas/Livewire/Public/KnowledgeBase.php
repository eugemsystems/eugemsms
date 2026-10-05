<?php

declare(strict_types=1);

namespace Modules\Saas\Livewire\Public;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Saas\Models\KnowledgeBaseArticle;

/**
 * `Success\KnowledgeBase\Index` (Book J SAA-03 §5, public / in-app). Help
 * articles, searchable by title and body — the equivalent of
 * `GET /api/v1/help/articles?search=`. Public, read-only: it performs no
 * writes (no view counters on a GET) and searches with bound parameters.
 */
#[Title('Help')]
#[Layout('saas::layouts.public')]
final class KnowledgeBase extends Component
{
    #[Url(as: 'search')]
    public string $search = '';

    public function render(): View
    {
        $term = mb_substr(trim($this->search), 0, 100);

        return view('saas::public.knowledge-base', [
            'articles' => KnowledgeBaseArticle::query()
                ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('title', 'like', '%'.addcslashes($term, '%_\\').'%')->orWhere('content', 'like', '%'.addcslashes($term, '%_\\').'%')))
                ->orderBy('title')->limit(100)->get(['id', 'slug', 'title', 'module_code', 'last_reviewed_on']),
        ]);
    }
}

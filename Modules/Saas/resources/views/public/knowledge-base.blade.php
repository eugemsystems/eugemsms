<div>
    <h3 class="mb-3">{{ __('Help') }}</h3>
    <input type="search" class="form-control mb-4" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search help articles') }}">
    @forelse ($articles as $article)
        <div class="card mb-2" wire:key="kb-{{ $article->id }}"><div class="card-body py-2 d-flex justify-content-between">
            <a href="{{ route('help.article', $article->slug) }}">{{ $article->title }}</a>
            <span class="small text-body-secondary">{{ $article->module_code }}</span>
        </div></div>
    @empty
        <div class="text-body-secondary">{{ __('No articles found.') }}</div>
    @endforelse
</div>

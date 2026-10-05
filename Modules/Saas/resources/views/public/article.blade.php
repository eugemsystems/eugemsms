<div>
    <a href="{{ route('help.index') }}" class="small" wire:navigate>← {{ __('All articles') }}</a>
    <h3 class="my-3">{{ $article->title }}</h3>
    <div class="article-body">{!! \Illuminate\Support\Str::markdown($article->content, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}</div>
    @if ($article->last_reviewed_on)
        <p class="small text-body-secondary mt-4">{{ __('Last reviewed :date', ['date' => $article->last_reviewed_on->toFormattedDateString()]) }}</p>
    @endif
</div>

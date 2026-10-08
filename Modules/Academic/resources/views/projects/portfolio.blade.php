<div>
    <h4 class="mb-1">{{ __('Project portfolio') }}</h4>
    <p class="text-body-secondary mb-4">{{ $learnerProject->student?->first_name }} {{ $learnerProject->student?->last_name }} — {{ __('status') }}: {{ ucfirst($learnerProject->status) }}</p>

    <div class="card">
        <div class="card-body">
            <p class="text-body-secondary small mb-3">{{ __('Compiles the brief, every milestone, all evidence, the rubric breakdown, and the marker/moderator comments into one document — generated fresh from source each time.') }}</p>
            <button type="button" class="btn btn-primary btn-sm" wire:click="compile" wire:loading.attr="disabled">{{ __('Compile portfolio') }}</button>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">{{ __('Compiled portfolios') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Compiled') }}</th><th>{{ __('Verification code') }}</th></tr></thead>
                <tbody>
                    @forelse ($portfolios as $portfolio)
                        <tr wire:key="portfolio-{{ $portfolio->id }}">
                            <td>{{ $portfolio->compiled_at->format('d M Y H:i') }}</td>
                            <td><code>{{ $portfolio->document?->verification_code }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-body-secondary py-4">{{ __('None compiled yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

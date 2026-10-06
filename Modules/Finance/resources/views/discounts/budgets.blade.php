<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Budget envelopes') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('What each scheme may spend in a year, what is committed in draft billing, what has actually posted, and what is left. A blank budget is uncapped.') }}</p>
    </div>
    <div class="mb-3"><select class="form-select form-select-sm w-auto" wire:model.live="academicYearId">@foreach ($years as $year) <option value="{{ $year->id }}">{{ $year->name }}</option> @endforeach</select></div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Scheme') }}</th><th class="text-end">{{ __('Budget') }}</th><th class="text-end">{{ __('Committed') }}</th><th class="text-end">{{ __('Utilised') }}</th><th class="text-end">{{ __('Remaining') }}</th></tr></thead>
                <tbody>
                    @forelse ($envelopes as $envelope)
                        <tr wire:key="env-{{ $envelope->id }}">
                            <td>{{ $envelope->scheme?->name }}</td>
                            <td class="text-end">{{ $envelope->budget_minor === null ? __('Uncapped') : number_format($envelope->budget_minor / 100, 2).' '.$envelope->currency }}</td>
                            <td class="text-end">{{ number_format($envelope->committed_minor / 100, 2) }}</td>
                            <td class="text-end">{{ number_format($envelope->utilised_minor / 100, 2) }}</td>
                            <td class="text-end">@if ($envelope->remainingMinor() === null) — @else <span class="{{ $envelope->remainingMinor() < 0 ? 'text-danger' : '' }}">{{ number_format($envelope->remainingMinor() / 100, 2) }}</span> @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No envelopes for this year.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('New envelope') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="schemeId"><option value="">{{ __('Scheme…') }}</option>@foreach ($schemes as $scheme) <option value="{{ $scheme->id }}">{{ $scheme->code }} — {{ $scheme->name }}</option> @endforeach</select>
            @error('schemeId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 mb-2"><div class="col-8"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="budget" placeholder="{{ __('Budget (blank = uncapped)') }}"></div><div class="col-4"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div></div>
            @error('budget') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
        </div></div></div>
    </div>
</div>

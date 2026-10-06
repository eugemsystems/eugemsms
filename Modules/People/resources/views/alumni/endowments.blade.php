<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Endowments') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('A donor’s fund behind a bursary scheme. The scheme’s budget follows the endowment’s available balance, and runs out like any capped scheme.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Endowment') }}</th><th>{{ __('Scheme') }}</th><th class="text-end">{{ __('Available') }}</th><th class="text-end">{{ __('Committed') }}</th><th class="text-end">{{ __('Utilised') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($endowments as $endowment)
                        @php($available = ($endowment->endowment_capital_minor ?? 0) + (int) ($donated[$endowment->id] ?? 0))
                        @php($envelope = $envelopes[$endowment->funds_scheme_id] ?? null)
                        <tr wire:key="en-{{ $endowment->id }}">
                            <td>{{ $endowment->named_recognition && ! $endowment->is_anonymous ? $endowment->named_recognition : $endowment->displayName() }}<div class="small text-body-secondary">{{ $endowment->displayName() }}</div></td>
                            <td>{{ $endowment->fundsScheme?->name }}</td>
                            <td class="text-end">{{ number_format($available / 100, 2) }} {{ $endowment->currency }}</td>
                            <td class="text-end">{{ $envelope ? number_format($envelope->committed_minor / 100, 2) : '—' }}</td>
                            <td class="text-end">{{ $envelope ? number_format($envelope->utilised_minor / 100, 2) : '—' }}</td>
                            <td>{{ __(ucfirst(str_replace('_', ' ', $endowment->status))) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No endowments.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('New endowment') }}</div><div class="card-body">
            <input type="text" class="form-control form-control-sm mb-2" wire:model="donorName" placeholder="{{ __('Donor') }}">
            @error('donorName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm mb-2" wire:model="fundsSchemeId"><option value="">{{ __('Scheme it funds…') }}</option>@foreach ($schemes as $scheme) <option value="{{ $scheme->id }}">{{ $scheme->code }} — {{ $scheme->name }}</option> @endforeach</select>
            @error('fundsSchemeId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 mb-2"><div class="col-5"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="capital" placeholder="{{ __('Capital') }}"></div><div class="col-5"><input type="number" step="0.01" min="0" class="form-control form-control-sm" wire:model="annual" placeholder="{{ __('Annual') }}"></div><div class="col-2"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div></div>
            <input type="text" class="form-control form-control-sm mb-2" wire:model="namedRecognition" placeholder="{{ __('Named recognition, e.g. The Moyo Family Bursary') }}">
            <input type="date" class="form-control form-control-sm mb-2" wire:model="startsOn">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="ea" wire:model="isAnonymous"><label class="form-check-label small" for="ea">{{ __('Donor wishes to be anonymous') }}</label></div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
        </div></div></div>
    </div>
</div>

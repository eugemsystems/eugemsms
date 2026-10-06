<div>
    <div class="mb-4">
        <h4 class="mb-0">{{ __('Discount schemes') }}</h4>
        <p class="text-body-secondary small mb-0">{{ __('Discounts, bursaries and scholarships. Only sibling and staff-child schemes run automatically; the rest are applied for or granted by a person.') }}</p>
    </div>
    <div class="row g-4">
        <div class="col-xl-7"><div class="card"><div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Category') }}</th><th>{{ __('Default') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($schemes as $scheme)
                        <tr wire:key="sc-{{ $scheme->id }}">
                            <td>{{ $scheme->code }}</td>
                            <td>{{ $scheme->name }} @if ($scheme->is_sponsor_funded) <span class="badge text-bg-info">{{ __('Sponsor-funded') }}</span> @endif @unless ($scheme->is_active) <span class="badge text-bg-secondary">{{ __('Closed') }}</span> @endunless</td>
                            <td class="small">{{ str_replace('_', ' ', $scheme->scheme_type) }}</td>
                            <td class="small">{{ str_replace('_', ' ', $scheme->category) }}</td>
                            <td class="small">@if ($scheme->tier_bands) {{ collect($scheme->tier_bands)->map(fn ($b) => $b['nth'].'→'.$b['percent'].'%')->implode(', ') }} @elseif ($scheme->default_percent) {{ $scheme->default_percent }}% @elseif ($scheme->default_amount_minor) {{ number_format($scheme->default_amount_minor / 100, 2) }} {{ $scheme->currency }} @else — @endif</td>
                            <td class="text-end">@if ($canManage) <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="setActive({{ $scheme->id }}, {{ $scheme->is_active ? 'false' : 'true' }})">{{ $scheme->is_active ? __('Close') : __('Reopen') }}</button> @endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No schemes yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        @if ($canManage)
        <div class="col-xl-5"><div class="card"><div class="card-header">{{ __('New scheme') }}</div><div class="card-body">
            <div class="row g-2 mb-2"><div class="col-4"><input type="text" class="form-control form-control-sm text-uppercase" wire:model="code" placeholder="SIBLING"></div><div class="col-8"><input type="text" class="form-control form-control-sm" wire:model="name" placeholder="{{ __('Name') }}"></div></div>
            @error('code') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            @error('name') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="row g-2 mb-2">
                <div class="col-6"><select class="form-select form-select-sm" wire:model="schemeType"><option value="individually_granted">{{ __('Individually granted') }}</option><option value="application_based">{{ __('Application-based') }}</option><option value="automatic">{{ __('Automatic') }}</option></select></div>
                <div class="col-6"><select class="form-select form-select-sm" wire:model="category">@foreach (['sibling', 'staff', 'academic', 'sport', 'hardship', 'orphan', 'corporate', 'church', 'early_settlement'] as $c) <option value="{{ $c }}">{{ str_replace('_', ' ', $c) }}</option> @endforeach</select></div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-4"><select class="form-select form-select-sm" wire:model="calculationMethod"><option value="percentage">{{ __('Percentage') }}</option><option value="fixed_amount">{{ __('Fixed') }}</option><option value="tiered">{{ __('Tiered') }}</option></select></div>
                <div class="col-4"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="defaultPercent" placeholder="%"></div>
                <div class="col-4"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="defaultAmount" placeholder="{{ __('Amount') }}"></div>
            </div>
            <div class="row g-2 mb-2"><div class="col-4"><input type="text" maxlength="3" class="form-control form-control-sm text-uppercase" wire:model="currency"></div><div class="col-8"><select class="form-select form-select-sm" wire:model="contraAccountId"><option value="">{{ __('Contra account (e.g. 4190)…') }}</option>@foreach ($accounts as $account) <option value="{{ $account->id }}">{{ $account->code }} {{ $account->name }}</option> @endforeach</select></div></div>
            @error('contraAccountId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <textarea class="form-control form-control-sm mb-2" rows="3" wire:model="tierBands" placeholder="{{ __('Sibling tier bands, one per line: 2=10 (2nd child 10%)') }}"></textarea>
            @error('tierBands') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <div class="small mb-1">{{ __('Applies to components (none = all)') }}</div>
            <select multiple class="form-select form-select-sm mb-2" size="4" wire:model="componentIds">@foreach ($components as $component) <option value="{{ $component->id }}">{{ $component->name }}</option> @endforeach</select>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="rm" wire:model="requiresMeans"><label class="form-check-label small" for="rm">{{ __('Requires means assessment') }}</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" id="ra" wire:model="requiresAcademic"><label class="form-check-label small" for="ra">{{ __('Requires an academic threshold') }}</label></div>
            @if ($requiresAcademic) <input type="number" step="0.01" class="form-control form-control-sm my-1" wire:model="minimumAverage" placeholder="{{ __('Minimum average %') }}"> @endif
            <div class="form-check"><input class="form-check-input" type="checkbox" id="rq" wire:model="requiresApproval"><label class="form-check-label small" for="rq">{{ __('Awards need approval') }}</label></div>
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="sp" wire:model="isSponsorFunded"><label class="form-check-label small" for="sp">{{ __('Sponsor-funded (bills the sponsor, no discount posted)') }}</label></div>
            <select class="form-select form-select-sm mb-2" wire:model="renewalFrequency"><option value="">{{ __('Renewal…') }}</option><option value="termly">{{ __('Termly') }}</option><option value="annual">{{ __('Annual') }}</option><option value="once">{{ __('Once') }}</option></select>
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create scheme') }}</button>
        </div></div></div>
        @endif
    </div>
</div>

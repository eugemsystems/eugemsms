<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Sponsorships') }}</h4><p class="text-body-secondary small mb-0">{{ __('An organisation funding learners. Their fees are invoiced to the sponsor; the school\'s income is unchanged.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Sponsorship') }}</th><th>{{ __('Sponsor') }}</th><th class="text-end">{{ __('Learners') }}</th><th class="text-end">{{ __('Committed / budget') }}</th><th>{{ __('Status') }}</th></tr></thead>
            <tbody>@forelse ($sponsorships as $s) <tr wire:key="s-{{ $s->id }}"><td><a href="{{ route('people.sponsorships.show', [$school, $s]) }}" wire:navigate>{{ $s->name }}</a></td><td>{{ $names->get($s->guardian_id) }}</td><td class="text-end">{{ $s->active_beneficiaries }}{{ $s->max_beneficiaries ? ' / '.$s->max_beneficiaries : '' }}</td><td class="text-end">{{ number_format($s->committed_minor / 100, 2) }}{{ $s->budget_minor ? ' / '.number_format($s->budget_minor / 100, 2).' '.$s->budget_currency : '' }}</td><td><span class="badge text-bg-light border">{{ $s->status }}</span></td></tr> @empty <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No sponsorships yet.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('New sponsorship') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="guardianId"><option value="">{{ __('Sponsoring organisation…') }}</option>@foreach ($sponsors as $sponsor) <option value="{{ $sponsor->id }}">{{ $sponsor->displayName() }}</option> @endforeach</select>
            @error('guardianId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="name" placeholder="{{ __('Name, e.g. Diocese Support 2026') }}">
            <select class="form-select form-select-sm mb-2" wire:model="type">@foreach (['full', 'partial', 'component_specific', 'capped'] as $t) <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option> @endforeach</select>
            <div class="row g-2 mb-2"><div class="col-6"><input type="number" step="0.01" class="form-control form-control-sm" wire:model="budget" placeholder="{{ __('Budget') }}"></div><div class="col-6"><input type="number" class="form-control form-control-sm" wire:model="maxBeneficiaries" placeholder="{{ __('Max learners') }}"></div></div>
            <input type="date" class="form-control form-control-sm mb-2" wire:model="startsOn">
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Create') }}</button>
        </div></div></div>
    </div>
</div>

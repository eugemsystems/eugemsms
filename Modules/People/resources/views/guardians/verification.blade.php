<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Guardian verification') }}</h4><p class="text-body-secondary small mb-0">{{ __('The ID document and photo the gate shows before a child is released. Recording and verifying are done by different people.') }}</p></div>
    <div class="row g-4">
        <div class="col-xl-7"><div class="card"><div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>{{ __('Guardian') }}</th><th>{{ __('Document') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>@forelse ($rows as $row) <tr wire:key="v-{{ $row->id }}"><td>{{ $names->get($row->guardian_id) }}</td><td>{{ str_replace('_', ' ', $row->document_type) }}</td><td>@if ($row->verified_at) <span class="badge text-bg-success">{{ __('verified') }} {{ $row->verified_at->format('d M Y') }}</span> @else <span class="badge text-bg-light border">{{ __('awaiting verification') }}</span> @endif</td><td class="text-end">@unless ($row->verified_at) <button type="button" class="btn btn-sm btn-outline-primary" wire:click="verify({{ $row->id }})">{{ __('Verify') }}</button> @endunless</td></tr> @empty <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('Nothing recorded yet.') }}</td></tr> @endforelse</tbody>
        </table></div></div></div>
        <div class="col-xl-5"><div class="card"><div class="card-header">{{ __('Record an ID check') }}</div><div class="card-body">
            @if ($picked) <div class="mb-2"><strong>{{ $picked->displayName() }}</strong> <button type="button" class="btn btn-link btn-sm" wire:click="$set('guardianId', null)">{{ __('change') }}</button></div>
            @else <input type="search" class="form-control form-control-sm mb-1" wire:model.live.debounce.300ms="search" placeholder="{{ __('Name or phone') }}">@foreach ($matches as $m) <button type="button" class="list-group-item list-group-item-action small border rounded mb-1" wire:key="g-{{ $m->id }}" wire:click="select({{ $m->id }})">{{ $m->displayName() }} {{ $m->primary_phone }}</button> @endforeach @endif
            @error('guardianId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <select class="form-select form-select-sm my-2" wire:model="documentType">@foreach (['national_id', 'passport', 'drivers_licence'] as $t) <option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option> @endforeach</select>
            <label class="small text-body-secondary">{{ __('ID document') }}</label><input type="file" class="form-control form-control-sm mb-2" wire:model="document">@error('document') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <label class="small text-body-secondary">{{ __('Collection photo') }}</label><input type="file" accept="image/*" class="form-control form-control-sm mb-2" wire:model="photo">@error('photo') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <button type="button" class="btn btn-primary btn-sm" wire:click="record" wire:loading.attr="disabled">{{ __('Record') }}</button>
        </div></div></div>
    </div>
</div>

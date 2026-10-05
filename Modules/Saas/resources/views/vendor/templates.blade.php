<div>
    <div class="mb-4"><h4 class="mb-0">{{ __('Template library') }}</h4><p class="text-body-secondary small mb-0">{{ __('Curated configuration profiles to start a school from. Applying is additive unless you choose to overwrite.') }}</p></div>
    @if ($lastResult) <div class="alert alert-success small">{{ $lastResult }}</div> @endif
    <div class="row g-4">
        <div class="col-xl-8"><div class="card"><div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>{{ __('Template') }}</th><th>{{ __('Profile') }}</th><th>{{ __('Suited for') }}</th><th class="text-end">{{ __('Used') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr wire:key="tp-{{ $template->id }}"><td>{{ $template->template_name }}</td><td class="small">{{ $profileNames[$template->configuration_profile_id] ?? '—' }}</td><td>{{ $template->suited_for ?? '—' }}</td><td class="text-end">{{ $template->used_count }}</td><td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" wire:click="beginApply({{ $template->id }})">{{ __('Apply') }}</button></td></tr>
                        @if ($applyingId === $template->id)
                            <tr wire:key="tp-ap-{{ $template->id }}"><td colspan="5">
                                <div class="row g-2 align-items-start">
                                    <div class="col-md-3"><select class="form-select form-select-sm" wire:model.live="tenantId"><option value="">{{ __('Tenant…') }}</option>@foreach ($tenants as $tenant) <option value="{{ $tenant->id }}">{{ $tenant->name }}</option> @endforeach</select></div>
                                    <div class="col-md-3"><select class="form-select form-select-sm" wire:model="schoolId"><option value="">{{ __('School…') }}</option>@foreach ($schools as $school) <option value="{{ $school->id }}">{{ $school->name }}</option> @endforeach</select></div>
                                    <div class="col-md-4">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ow-s" wire:model="overwriteSettings"><label class="form-check-label small" for="ow-s">{{ __('Overwrite existing settings') }}</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="ow-c" wire:model="overwriteCustomFields"><label class="form-check-label small" for="ow-c">{{ __('Overwrite existing custom fields') }}</label></div>
                                    </div>
                                    <div class="col-md-2"><button type="button" class="btn btn-primary btn-sm" wire:click="apply" wire:confirm="{{ __('Apply this template to the school?') }}">{{ __('Apply') }}</button></div>
                                </div>
                            </td></tr>
                        @endif
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('The library is empty.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div></div></div>
        <div class="col-xl-4"><div class="card"><div class="card-header">{{ __('Add a template') }}</div><div class="card-body">
            <select class="form-select form-select-sm mb-2" wire:model="profileId"><option value="">{{ __('Configuration profile…') }}</option>@foreach ($profiles as $profile) <option value="{{ $profile->id }}">{{ $profile->name }}</option> @endforeach</select>
            @error('profileId') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="templateName" placeholder="{{ __('Template name') }}">
            @error('templateName') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
            <input type="text" class="form-control form-control-sm mb-2" wire:model="suitedFor" placeholder="boarding_secondary">
            <button type="button" class="btn btn-primary btn-sm" wire:click="create">{{ __('Add') }}</button>
        </div></div></div>
    </div>
</div>

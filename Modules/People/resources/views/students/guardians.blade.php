<div>
    <h4 class="mb-1">{{ __('Manage guardians') }}</h4>
    <p class="text-body-secondary mb-4">{{ $student->fullName() }} — {{ $student->admission_number }}</p>

    <div class="card mb-4">
        <div class="card-header">{{ __('Linked guardians') }}</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Guardian') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($links as $link)
                        <tr wire:key="link-{{ $link->id }}">
                            <td><a href="{{ route('people.guardians.show', [$school, $link->guardian]) }}" wire:navigate>{{ $link->guardian?->displayName() }}</a></td>
                            <td>{{ ucfirst($link->relationship) }}</td>
                            <td>
                                @if ($link->is_primary_contact) <span class="badge text-bg-primary">{{ __('Primary') }}</span> @endif
                                @if ($link->is_fee_responsible) <span class="badge text-bg-success">{{ __('Fee responsible') }}</span> @endif
                                @if ($link->is_emergency_contact) <span class="badge text-bg-warning">{{ __('Emergency') }}</span> @endif
                                @if ($link->may_collect_learner) <span class="badge text-bg-info">{{ __('May collect') }}</span> @endif
                                @if ($link->has_court_restriction) <span class="badge text-bg-danger">{{ __('Court restriction') }}</span> @endif
                            </td>
                            <td><span class="badge {{ $link->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ ucfirst($link->status) }}</span></td>
                            <td class="text-end">
                                @if ($link->status === 'active')
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deactivate({{ $link->id }})" wire:confirm="{{ __('Deactivate this guardian relationship?') }}">{{ __('Deactivate') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No guardians linked yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">{{ __('Link a guardian') }}</div>
        <div class="card-body">
            <p class="text-body-secondary small">
                {{ __('Guardian not in the list yet?') }}
                <a href="{{ route('people.guardians.create', $school) }}" wire:navigate>{{ __('Create one first') }}</a>.
            </p>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select @error('guardianId') is-invalid @enderror" wire:model="guardianId">
                            <option value="">{{ __('Select a guardian') }}</option>
                            @foreach ($guardians as $guardian)
                                <option value="{{ $guardian->id }}">{{ $guardian->displayName() }}</option>
                            @endforeach
                        </select>
                        <label>{{ __('Guardian') }}</label>
                        @error('guardianId') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <select class="form-select" wire:model="relationship">
                            <option value="mother">{{ __('Mother') }}</option>
                            <option value="father">{{ __('Father') }}</option>
                            <option value="guardian">{{ __('Guardian') }}</option>
                            <option value="grandparent">{{ __('Grandparent') }}</option>
                            <option value="sibling">{{ __('Sibling') }}</option>
                            <option value="foster_carer">{{ __('Foster carer') }}</option>
                            <option value="employer">{{ __('Employer') }}</option>
                            <option value="sponsor">{{ __('Sponsor') }}</option>
                            <option value="other">{{ __('Other') }}</option>
                        </select>
                        <label>{{ __('Relationship') }}</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-floating form-floating-outline">
                        <input type="date" class="form-control @error('effectiveFrom') is-invalid @enderror" wire:model="effectiveFrom">
                        <label>{{ __('Effective from') }}</label>
                        @error('effectiveFrom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="isPrimaryContact" wire:model="isPrimaryContact">
                            <label class="form-check-label" for="isPrimaryContact">{{ __('Primary contact') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="isEmergencyContact" wire:model="isEmergencyContact">
                            <label class="form-check-label" for="isEmergencyContact">{{ __('Emergency contact') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="isFeeResponsible" wire:model="isFeeResponsible">
                            <label class="form-check-label" for="isFeeResponsible">{{ __('Fee responsible') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="mayCollectLearner" wire:model="mayCollectLearner">
                            <label class="form-check-label" for="mayCollectLearner">{{ __('May collect learner') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="mayViewFullBalance" wire:model="mayViewFullBalance">
                            <label class="form-check-label" for="mayViewFullBalance">{{ __('May view full balance') }}</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="hasCourtRestriction" wire:model="hasCourtRestriction">
                            <label class="form-check-label" for="hasCourtRestriction">{{ __('Court restriction (overrides collection rights)') }}</label>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-primary mt-4" wire:click="link" wire:loading.attr="disabled">{{ __('Link guardian') }}</button>
        </div>
    </div>
</div>

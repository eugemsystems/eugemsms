<div>
    <h4 class="mb-1">{{ __('Curriculum frameworks') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    @if ($unconfirmedRules->isNotEmpty())
        <div class="alert alert-warning">
            <strong>{{ __('Confirm against your current MoPSE circular.') }}</strong>
            {{ __('The following selection rules are flagged for review before first use this year:') }}
            <ul class="mb-0 mt-1">
                @foreach ($unconfirmedRules as $rule)
                    <li>{{ $rule->message }} @if ($rule->source_reference) <span class="text-body-secondary">({{ $rule->source_reference }})</span> @endif</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Frameworks') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Authority') }}</th><th>{{ __('CA model') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($frameworks as $framework)
                                <tr wire:key="framework-{{ $framework->id }}">
                                    <td>{{ $framework->code }}</td>
                                    <td>{{ $framework->name }}</td>
                                    <td>{{ $framework->authority }}</td>
                                    <td>{{ strtoupper($framework->continuous_assessment_model) }}</td>
                                    <td><span class="badge text-bg-{{ $framework->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($framework->status) }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No frameworks yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New framework') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder=" ">
                                    <label>{{ __('Code') }}</label>
                                    @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('authority') is-invalid @enderror" wire:model="authority" placeholder=" ">
                                    <label>{{ __('Authority') }}</label>
                                    @error('authority') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control @error('effectiveFrom') is-invalid @enderror" wire:model="effectiveFrom">
                                    <label>{{ __('Effective from') }}</label>
                                    @error('effectiveFrom') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="continuousAssessmentModel">
                                        <option value="sbp">{{ __('SBP') }}</option>
                                        <option value="cala">{{ __('CALA') }}</option>
                                        <option value="coursework">{{ __('Coursework') }}</option>
                                        <option value="none">{{ __('None') }}</option>
                                    </select>
                                    <label>{{ __('Continuous assessment model') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="referenceCircular" placeholder=" ">
                                    <label>{{ __('Reference circular (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <textarea class="form-control" wire:model="notes" placeholder=" " style="height: 80px"></textarea>
                                    <label>{{ __('Notes (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create framework') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

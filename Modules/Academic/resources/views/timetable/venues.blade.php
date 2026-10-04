<div>
    <h4 class="mb-1">{{ __('Venues') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Venues') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Capacity') }}</th><th>{{ __('Exam capacity') }}</th></tr></thead>
                        <tbody>
                            @forelse ($venues as $venue)
                                <tr wire:key="venue-{{ $venue->id }}">
                                    <td>{{ $venue->code }}</td>
                                    <td>{{ $venue->name }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $venue->venue_type)) }}</td>
                                    <td>{{ $venue->capacity }}</td>
                                    <td>{{ $venue->exam_capacity ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No venues yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New venue') }}</div>
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
                                    <select class="form-select" wire:model="venueType">
                                        <option value="classroom">{{ __('Classroom') }}</option>
                                        <option value="laboratory">{{ __('Laboratory') }}</option>
                                        <option value="workshop">{{ __('Workshop') }}</option>
                                        <option value="computer_lab">{{ __('Computer lab') }}</option>
                                        <option value="hall">{{ __('Hall') }}</option>
                                        <option value="field">{{ __('Field') }}</option>
                                        <option value="library">{{ __('Library') }}</option>
                                        <option value="music_room">{{ __('Music room') }}</option>
                                        <option value="art_room">{{ __('Art room') }}</option>
                                    </select>
                                    <label>{{ __('Venue type') }}</label>
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
                                    <input type="number" class="form-control @error('capacity') is-invalid @enderror" wire:model="capacity" min="1">
                                    <label>{{ __('Capacity') }}</label>
                                    @error('capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="number" class="form-control" wire:model="examCapacity" min="1">
                                    <label>{{ __('Exam capacity (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="building" placeholder=" ">
                                    <label>{{ __('Building (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="floor" placeholder=" ">
                                    <label>{{ __('Floor (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create venue') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

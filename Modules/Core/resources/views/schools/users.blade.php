<div>
    @include('core::schools.partials.tabs', ['school' => $school, 'active' => 'users'])

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ __('Assign a user') }}</h5>
                    <form wire:submit="assign">
                        <div class="form-floating form-floating-outline mb-3">
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" wire:model="email" placeholder=" ">
                            <label for="email">{{ __('User email') }}</label>
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="isPrimary" wire:model="isPrimary">
                            <label class="form-check-label" for="isPrimary">{{ __('Make this their primary school') }}</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">{{ __('Assign') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Email') }}</th>
                                <th>{{ __('Primary') }}</th>
                                <th class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($assignedUsers as $assignedUser)
                                <tr>
                                    <td>{{ $assignedUser->name }}</td>
                                    <td>{{ $assignedUser->email }}</td>
                                    <td>
                                        @if ($assignedUser->pivot->is_primary)
                                            <span class="badge text-bg-primary">{{ __('Primary') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @unless ($assignedUser->pivot->is_primary)
                                            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="makePrimary({{ $assignedUser->id }})">
                                                {{ __('Make primary') }}
                                            </button>
                                        @endunless
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-body-secondary py-4">{{ __('No users assigned yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div>
    <h4 class="mb-1">{{ __('Establishment') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('Departments') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th></tr></thead>
                        <tbody>
                            @forelse ($departments as $department)
                                <tr>
                                    <td>{{ $department->code }}</td>
                                    <td>{{ $department->name }}</td>
                                    <td>{{ ucfirst($department->type) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No departments yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('New department') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm @error('departmentCode') is-invalid @enderror" wire:model="departmentCode" placeholder="{{ __('Code') }}">
                        </div>
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm @error('departmentName') is-invalid @enderror" wire:model="departmentName" placeholder="{{ __('Name') }}">
                        </div>
                        <div class="col-12">
                            <select class="form-select form-select-sm" wire:model="departmentType">
                                <option value="academic">{{ __('Academic') }}</option>
                                <option value="administrative">{{ __('Administrative') }}</option>
                                <option value="operational">{{ __('Operational') }}</option>
                            </select>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="createDepartment">{{ __('Create department') }}</button>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">{{ __('Establishment posts') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Department') }}</th><th>{{ __('Filled / Approved') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($posts as $post)
                                <tr wire:key="post-{{ $post->id }}">
                                    <td>{{ $post->title }}</td>
                                    <td>{{ $post->department?->name ?? '—' }}</td>
                                    <td>
                                        {{ $post->filled_count }} / {{ $post->approved_count }}
                                        @if (! $post->hasVacancy()) <span class="badge text-bg-warning">{{ __('Full') }}</span> @endif
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="fillPost({{ $post->id }})">{{ __('Fill') }}</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="vacate({{ $post->id }})">{{ __('Vacate') }}</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-body-secondary py-3">{{ __('No posts yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="overrideEstablishment" wire:model="overrideEstablishment">
                        <label class="form-check-label small" for="overrideEstablishment">{{ __('Override capacity when filling a full post (BR-PPL-04-004)') }}</label>
                    </div>
                    @if ($overrideEstablishment)
                        <input type="text" class="form-control form-control-sm mt-2" wire:model="overrideReason" placeholder="{{ __('Override reason') }}">
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">{{ __('New post') }}</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-8">
                            <input type="text" class="form-control form-control-sm @error('postTitle') is-invalid @enderror" wire:model="postTitle" placeholder="{{ __('Title') }}">
                        </div>
                        <div class="col-md-4">
                            <input type="number" class="form-control form-control-sm @error('postApprovedCount') is-invalid @enderror" wire:model="postApprovedCount" placeholder="{{ __('Approved count') }}">
                        </div>
                        <div class="col-md-8">
                            <select class="form-select form-select-sm" wire:model="postDepartmentId">
                                <option value="">{{ __('No department') }}</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-check d-flex align-items-center">
                            <input type="checkbox" class="form-check-input" id="postIsTeaching" wire:model="postIsTeaching">
                            <label class="form-check-label small ms-2" for="postIsTeaching">{{ __('Teaching') }}</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm mt-3" wire:click="createPost">{{ __('Create post') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>

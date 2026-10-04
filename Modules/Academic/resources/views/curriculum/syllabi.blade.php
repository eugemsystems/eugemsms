<div>
    <h4 class="mb-1">{{ __('Syllabus repository') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Metadata only in this pass — document upload is not wired here.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Syllabi') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Title') }}</th><th>{{ __('Version') }}</th></tr></thead>
                        <tbody>
                            @forelse ($syllabi as $syllabus)
                                <tr wire:key="syllabus-{{ $syllabus->id }}">
                                    <td>{{ $syllabus->subject?->name }}</td>
                                    <td>{{ $syllabus->title }}</td>
                                    <td>{{ $syllabus->version ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('No syllabi yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New syllabus entry') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select @error('subjectId') is-invalid @enderror" wire:model="subjectId">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Subject') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="frameworkId">
                                        @foreach ($frameworks as $framework)
                                            <option value="{{ $framework->id }}">{{ $framework->code }}</option>
                                        @endforeach
                                    </select>
                                    <label>{{ __('Framework') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" wire:model="title" placeholder=" ">
                                    <label>{{ __('Title') }}</label>
                                    @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="version" placeholder=" ">
                                    <label>{{ __('Version (optional)') }}</label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Add syllabus') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

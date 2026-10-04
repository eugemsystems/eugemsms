<div>
    <h4 class="mb-1">{{ __('Comment bank') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Suggested comments by subject, grade band, or conduct — manages the bank only.') }}</p>

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">{{ __('Bank entries') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Scope') }}</th><th>{{ __('Text') }}</th><th>{{ __('Used') }}</th></tr></thead>
                        <tbody>
                            @forelse ($comments as $comment)
                                <tr wire:key="comment-{{ $comment->id }}">
                                    <td>{{ ucfirst($comment->scope) }}{{ $comment->grade_band ? " ({$comment->grade_band})" : '' }}</td>
                                    <td>{{ $comment->text }}</td>
                                    <td>{{ $comment->usage_count }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('No comments in the bank yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New comment') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model.live="scope">
                                        <option value="subject">{{ __('Subject') }}</option>
                                        <option value="general">{{ __('General') }}</option>
                                        <option value="conduct">{{ __('Conduct') }}</option>
                                    </select>
                                    <label>{{ __('Scope') }}</label>
                                </div>
                            </div>
                            @if ($scope === 'subject')
                                <div class="col-md-6">
                                    <div class="form-floating form-floating-outline">
                                        <select class="form-select" wire:model="subjectId">
                                            <option value="">{{ __('Select') }}</option>
                                            @foreach ($subjects as $subject)
                                                <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                            @endforeach
                                        </select>
                                        <label>{{ __('Subject') }}</label>
                                    </div>
                                </div>
                            @endif
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control" wire:model="gradeBand" placeholder=" ">
                                    <label>{{ __('Grade band (optional)') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <textarea class="form-control @error('text') is-invalid @enderror" wire:model="text" placeholder=" " style="height: 90px"></textarea>
                                    <label>{{ __('Comment text') }}</label>
                                    @error('text') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Add comment') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

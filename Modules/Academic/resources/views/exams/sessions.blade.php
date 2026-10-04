<div>
    <h4 class="mb-1">{{ __('Examination sessions') }}</h4>
    <p class="text-body-secondary mb-4">{{ $school->name }}</p>

    @php
        $sequence = ['planning', 'entries_open', 'entries_closed', 'scheduled', 'in_progress', 'marking', 'moderation'];
    @endphp

    <div class="row g-4">
        <div class="col-md-7">
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Body') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($sessions as $session)
                                @php $nextIndex = array_search($session->status, $sequence, true); @endphp
                                <tr wire:key="session-{{ $session->id }}">
                                    <td>{{ $session->name }}</td>
                                    <td>{{ strtoupper($session->exam_body) }}</td>
                                    <td>{{ $session->starts_on->toFormattedDateString() }} – {{ $session->ends_on->toFormattedDateString() }}</td>
                                    <td><span class="badge text-bg-secondary">{{ ucfirst($session->status) }}</span></td>
                                    <td>
                                        @if ($nextIndex !== false && $nextIndex < count($sequence) - 1)
                                            <button type="button" class="btn btn-sm btn-outline-primary" wire:click="advance({{ $session->id }}, '{{ $sequence[$nextIndex + 1] }}')">
                                                {{ __('Advance to :status', ['status' => $sequence[$nextIndex + 1]]) }}
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No examination sessions yet.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">{{ __('New session') }}</div>
                <div class="card-body">
                    <form wire:submit="create">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-floating form-floating-outline">
                                    <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder=" ">
                                    <label>{{ __('Name') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="examType">
                                        <option value="end_of_term">{{ __('End of term') }}</option>
                                        <option value="mid_term">{{ __('Mid term') }}</option>
                                        <option value="mock">{{ __('Mock') }}</option>
                                        <option value="entrance">{{ __('Entrance') }}</option>
                                        <option value="public">{{ __('Public') }}</option>
                                        <option value="resit">{{ __('Resit') }}</option>
                                    </select>
                                    <label>{{ __('Exam type') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <select class="form-select" wire:model="examBody">
                                        <option value="internal">{{ __('Internal') }}</option>
                                        <option value="zimsec">{{ __('ZIMSEC') }}</option>
                                        <option value="cambridge">{{ __('Cambridge') }}</option>
                                    </select>
                                    <label>{{ __('Exam body') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control" wire:model="startsOn">
                                    <label>{{ __('Starts on') }}</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-floating form-floating-outline">
                                    <input type="date" class="form-control" wire:model="endsOn">
                                    <label>{{ __('Ends on') }}</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('Affected levels') }}</label>
                                <div class="row">
                                    @foreach ($levels as $level)
                                        <div class="col-md-6 form-check">
                                            <input type="checkbox" class="form-check-input" wire:model="affectedLevels" value="{{ $level->id }}" id="elevel-{{ $level->id }}">
                                            <label class="form-check-label" for="elevel-{{ $level->id }}">{{ $level->name }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3" wire:loading.attr="disabled">{{ __('Create session') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

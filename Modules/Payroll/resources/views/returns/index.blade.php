<div>
    <h4 class="mb-1">{{ __('Statutory returns') }} 🇿🇼</h4>
    <p class="text-body-secondary small">{{ __('Prepared automatically on posting. The system prepares and exports — it does not file. Submission is recorded manually.') }}</p>

    <button type="button" class="btn btn-outline-info btn-sm mb-3" wire:click="checkDeadlines">{{ __('Check deadlines now') }}</button>

    @if ($checked)
        <div class="alert {{ $overdueCount > 0 ? 'alert-danger' : ($dueCount > 0 ? 'alert-warning' : 'alert-success') }}">
            {{ __('Due soon:') }} {{ $dueCount }} — {{ __('Overdue:') }} {{ $overdueCount }}
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Period') }}</th><th>{{ __('Due') }}</th><th>{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($returns as $return)
                        <tr wire:key="return-{{ $return->id }}">
                            <td>{{ $return->return_type }}</td>
                            <td>{{ $return->period_reference }}</td>
                            <td>{{ $return->due_date->toDateString() }}</td>
                            <td>{{ number_format($return->amount_due_minor / 100, 2) }} {{ $return->currency }}</td>
                            <td><span class="badge bg-secondary">{{ $return->status }}</span></td>
                            <td>
                                @if (! in_array($return->status, ['submitted', 'acknowledged', 'paid']))
                                    <button type="button" class="btn btn-outline-success btn-sm" wire:click="startRecording({{ $return->id }})">{{ __('Record submission') }}</button>
                                @else
                                    <span class="small text-body-secondary">{{ $return->submission_reference }}</span>
                                @endif
                            </td>
                        </tr>
                        @if ($recordingReturnId === $return->id)
                            <tr>
                                <td colspan="6">
                                    <input type="text" class="form-control form-control-sm d-inline-block mb-0" style="width:auto" wire:model="submissionReference" placeholder="{{ __('Submission reference') }}">
                                    <button type="button" class="btn btn-sm btn-success" wire:click="recordSubmission">{{ __('Save') }}</button>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-3">{{ __('No statutory returns yet — prepared automatically when a payroll run posts.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

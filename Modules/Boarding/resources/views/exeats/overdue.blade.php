<div>
    <h4 class="mb-1">{{ __('Overdue returns') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('An exeat overdue beyond the configured threshold opens a BRD-02 missing-learner incident automatically.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Learner') }}</th><th>{{ __('Type') }}</th><th>{{ __('Returns by') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($overdue as $exeat)
                        <tr>
                            <td>{{ $exeat->student->first_name }} {{ $exeat->student->last_name }}</td>
                            <td>{{ $exeat->exeatType->name }}</td>
                            <td>{{ $exeat->returns_by->format('Y-m-d H:i') }}</td>
                            <td><span class="badge text-bg-danger">{{ ucfirst($exeat->status) }}</span></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" wire:click="checkNow({{ $exeat->id }})">{{ __('Check now') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-3">{{ __('No overdue exeats.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

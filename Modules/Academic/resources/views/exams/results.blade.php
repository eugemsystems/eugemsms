<div>
    <h4 class="mb-1">{{ __('Examination results') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Process aggregates paper marks into the ACA-05 pipeline; publish is a separate, staged step.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Session') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($sessions as $session)
                        <tr wire:key="session-{{ $session->id }}">
                            <td>{{ $session->name }}</td>
                            <td><span class="badge text-bg-secondary">{{ ucfirst($session->status) }}</span></td>
                            <td>
                                @if (in_array($session->status, ['in_progress', 'marking', 'moderation'], true))
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="process({{ $session->id }})">{{ __('Process results') }}</button>
                                @elseif ($session->status === 'results_ready')
                                    <button type="button" class="btn btn-sm btn-success" wire:click="publish({{ $session->id }})">{{ __('Publish') }}</button>
                                @elseif ($session->status === 'published')
                                    <span class="badge text-bg-success">{{ __('Published') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-4">{{ __('No sessions ready for results processing.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

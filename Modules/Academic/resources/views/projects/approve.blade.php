<div>
    <h4 class="mb-1">{{ __('Brief approval') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('HOD sign-off. A teacher cannot set a project unilaterally.') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Level') }}</th><th>{{ __('Status') }}</th><th>{{ __('Action') }}</th></tr></thead>
                <tbody>
                    @forelse ($briefs as $brief)
                        <tr wire:key="brief-{{ $brief->id }}">
                            <td>{{ $brief->title }}</td>
                            <td>{{ $brief->subject?->name }}</td>
                            <td>{{ $brief->gradeLevel?->name }}</td>
                            <td><span class="badge text-bg-secondary">{{ ucfirst($brief->status) }}</span></td>
                            <td>
                                @if ($brief->status === 'draft')
                                    <button type="button" class="btn btn-sm btn-primary" wire:click="approve({{ $brief->id }})">{{ __('Approve') }}</button>
                                @elseif ($brief->status === 'approved')
                                    <button type="button" class="btn btn-sm btn-success" wire:click="issue({{ $brief->id }})">{{ __('Issue') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary py-4">{{ __('No briefs awaiting approval or issue.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

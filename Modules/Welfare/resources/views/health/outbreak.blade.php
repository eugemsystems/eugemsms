<div>
    <h4 class="mb-1">{{ __('Outbreak monitor') }}</h4>
    <p class="text-body-secondary mb-4">{{ __('Aggregate only. Admissions by presenting complaint in the last :days days — threshold :threshold triggers the nurse/head alert automatically on admission.', ['days' => $windowDays, 'threshold' => $threshold]) }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Presenting complaint') }}</th><th>{{ __('Cases') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($clusters as $cluster)
                        <tr>
                            <td>{{ $cluster->presenting_complaint }}</td>
                            <td>{{ $cluster->case_count }}</td>
                            <td>
                                @if ($cluster->case_count >= $threshold)
                                    <span class="badge text-bg-danger">{{ __('Threshold reached') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-body-secondary py-3">{{ __('No admissions in the window.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

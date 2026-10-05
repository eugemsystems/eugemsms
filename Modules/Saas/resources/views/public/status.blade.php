<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head', ['title' => __('System status')])</head>
    <body>
        <main class="container py-5" style="max-width: 760px">
            <h3 class="mb-1">{{ __('System status') }}</h3>
            @php($open = $incidents->where('status', '!=', 'resolved'))
            <p class="mb-4 {{ $open->isEmpty() ? 'text-success' : 'text-warning' }}">{{ $open->isEmpty() ? __('All systems operational.') : trans_choice(':count active incident|:count active incidents', $open->count()) }}</p>
            @foreach ($incidents as $incident)
                <div class="card mb-3">
                    <div class="card-header d-flex flex-wrap gap-2 align-items-center"><strong>{{ $incident->title }}</strong><span class="badge text-bg-{{ $incident->status === 'resolved' ? 'success' : 'warning' }}">{{ __(ucfirst($incident->status)) }}</span><span class="small text-body-secondary ms-auto">{{ implode(', ', $incident->affected_components) }}</span></div>
                    <div class="card-body small">
                        @foreach (array_reverse($incident->updates) as $update)
                            <div class="mb-1"><span class="text-body-secondary">{{ \Illuminate\Support\Carbon::parse($update['at'])->toDayDateTimeString() }}</span> · <strong>{{ __(ucfirst($update['status'])) }}</strong> — {{ $update['message'] }}</div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </main>
    </body>
</html>

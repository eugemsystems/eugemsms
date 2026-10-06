<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head', ['title' => __('Developers')])</head>
    <body>
        <main class="container py-5" style="max-width: 900px">
            <h3 class="mb-1">{{ __('Developer documentation') }}</h3>
            <p class="text-body-secondary">{{ __('Public API :version. The machine-readable specification is generated from the live routes: :link.', ['version' => $document['info']['version'], 'link' => '']) }}<a href="{{ route('api.openapi') }}">/api/v1/openapi.json</a></p>

            <h5 class="mt-4">{{ __('Authentication') }}</h5>
            <p>{{ __('Send your API key as a bearer token: Authorization: Bearer {key}. Keys are issued by a school administrator, scoped to an explicit list of abilities, and shown once.') }}</p>

            <h5 class="mt-4">{{ __('Rate limits') }}</h5>
            <p>{{ __('Each key has its own per-minute limit. Beyond it the API answers 429 with a Retry-After header; requests are never silently dropped.') }}</p>

            <h5 class="mt-4">{{ __('Endpoints') }}</h5>
            @foreach ($document['paths'] as $path => $operations)
                @foreach ($operations as $method => $operation)
                    <div class="card mb-2"><div class="card-body py-2">
                        <span class="badge text-bg-secondary">{{ strtoupper($method) }}</span>
                        <code>{{ $path }}</code>
                        @isset($operation['x-required-abilities'])<span class="small text-body-secondary ms-2">{{ implode(', ', $operation['x-required-abilities']) }}</span>@endisset
                        <div class="small text-body-secondary">{{ $operation['summary'] }}</div>
                    </div></div>
                @endforeach
            @endforeach
        </main>
    </body>
</html>

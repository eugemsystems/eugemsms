<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>@include('partials.head', ['title' => $title ?? null])</head>
    <body>
        <main class="container py-5" style="max-width: 820px">
            {{ $slot }}
        </main>
    </body>
</html>

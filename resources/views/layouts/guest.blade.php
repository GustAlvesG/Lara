<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />
    <title>{{ config('app.name', 'Laravel') }}</title>
    @include('partials.theme-script')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|unbounded:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-canvas font-sans text-ink antialiased">
    <div class="flex flex-1 items-center justify-center p-4">
        {{ $slot }}
    </div>

    @include('partials.footer')
</body>
</html>

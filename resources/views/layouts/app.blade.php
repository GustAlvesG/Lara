<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Icon -->
        <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/webcamjs/1.0.25/webcam.min.js"></script>
        <!-- Bootstrap Grid -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap-grid.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- @vite(['resources/sass/app.scss', 'resources/js/app.js']) --}}

        <link rel="stylesheet" href="{{ asset('css/style-global.css') }}">
        {{ $css ?? '' }}
    </head>
    <body class="font-sans antialiased">
        <div
            x-data="{
                collapsed: window.matchMedia('(max-width: 640px)').matches ? false : (localStorage.getItem('sidebarCollapsed') === 'true'),
                mobileOpen: false,
                toggle() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('sidebarCollapsed', this.collapsed);
                }
            }"
            class="min-h-screen bg-gray-100 dark:bg-gray-900"
        >
            @include('partials.navigation')

            <!-- Content shifted by the sidebar width -->
            <div
                class="transition-all duration-300 ease-in-out"
                :class="collapsed ? 'sm:ml-20' : 'sm:ml-64'"
            >
                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white dark:bg-gray-800 shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- JQUERY --}}
        <script
            src="https://code.jquery.com/jquery-3.7.1.js"
            integrity="sha256-eKhayi8LEQwp4NKxN+CfCh+3qOVUtJn3QNZ0TciWLP4="
            crossorigin="anonymous"></script>
        {{ $js ?? '' }}

        @auth
        <script>
        (function () {
            const POLL_MS  = 30000;
            const ICON_URL = '{{ asset("favicon.ico") }}';
            let since = new Date().toISOString();

            function requestPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
            }

            function showNotification(title, body, url) {
                if (!('Notification' in window) || Notification.permission !== 'granted') return;
                const n = new Notification(title, { body: body, icon: ICON_URL });
                n.onclick = function () {
                    window.focus();
                    if (url) window.location.href = url;
                    n.close();
                };
            }

            async function poll() {
                try {
                    const res = await fetch('/notifications/unread-json?since=' + encodeURIComponent(since), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    since = data.checked_at;
                    data.notifications.forEach(function (n) {
                        showNotification(n.title, n.message, n.url);
                    });
                } catch (_) {}
            }

            requestPermission();
            setInterval(poll, POLL_MS);
        })();
        </script>
        @endauth
    </body>
</html>

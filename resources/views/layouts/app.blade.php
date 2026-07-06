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
        <style>[x-cloak]{display:none!important;}</style>
        {{ $css ?? '' }}
    </head>
    <body class="font-sans antialiased">
        @php
            $navLinks = [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6'],
                ['route' => 'information.index', 'label' => 'InfoClube', 'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                    'permission' => 'view information',
                    'children' => [
                        ['route' => 'information.index', 'label' => 'Informações'],
                        ['route' => 'avisos.index', 'label' => 'Avisos'],
                    ],
                ],
                ['route' => 'parking.search', 'label' => 'SIV', 'icon' => 'M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z',
                    'permission' => 'search parking',
                    'children' => [
                        ['route' => 'parking.search', 'label' => 'Busca'],
                        ['route' => 'parking-authorizations.index', 'label' => 'Placas Diretoria'],
                    ],
                ],
                ['route' => 'videowall.index', 'label' => 'Smart Panel', 'icon' => 'M9.75 17L9 20l-1-1v-4h-2l-1 1 7-7 7 7-1 1h-2v-4l-1 1h-2v4z',
                    'permission' => 'manage smart panel',
                ],
                ['route' => 'schedule.index', 'label' => 'Reservas', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                    'permission' => 'view reservations',
                ],
                ['route' => 'company.index', 'label' => 'Parceiros', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
                    'children' => [
                        ['route' => 'company.index', 'label' => 'Empresas'],
                        ['route' => 'company.access.monitor', 'label' => 'Monitor de Acesso'],
                        ['route' => 'company.access.logs', 'label' => 'Histórico'],
                    ],
                ],
            ];
        @endphp

        <div
            x-data="{
                collapsed: window.matchMedia('(max-width: 640px)').matches ? false : (localStorage.getItem('sidebarCollapsed') === 'true'),
                mobileOpen: false,
                navMode: localStorage.getItem('navMode') || 'side',
                toggle() {
                    this.collapsed = !this.collapsed;
                    localStorage.setItem('sidebarCollapsed', this.collapsed);
                },
                setNav(m) {
                    this.navMode = m;
                    localStorage.setItem('navMode', m);
                    this.mobileOpen = false;
                }
            }"
            class="min-h-screen bg-gray-100 dark:bg-gray-900"
        >
            <!-- Lateral navigation -->
            <div x-show="navMode === 'side'" x-cloak>
                @include('partials.navigation')
            </div>

            <!-- Top navigation -->
            <div x-show="navMode === 'top'" x-cloak>
                @include('partials.navigation-top')
            </div>

            <!-- Content -->
            <div
                class="transition-all duration-300 ease-in-out"
                :class="navMode === 'side' ? (collapsed ? 'sm:ml-20' : 'sm:ml-64') : ''"
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

            <!-- Floating notification bell (fixed bottom-right, both modes) -->
            @include('partials.notification-bell')
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

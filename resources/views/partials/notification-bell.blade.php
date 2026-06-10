@php
    $unreadNotifications = auth()->user()->unreadNotifications()->latest()->limit(8)->get();
    $unreadCount = $unreadNotifications->count();
@endphp

<div x-data="{ bellOpen: false }" class="relative flex items-center">

    {{-- Bell button --}}
    <button @click="bellOpen = !bellOpen" @click.outside="bellOpen = false"
        class="relative p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unreadCount > 0)
            <span class="absolute top-1 right-1 w-4 h-4 text-xs flex items-center justify-center bg-red-600 text-white rounded-full font-bold leading-none">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div x-show="bellOpen" x-transition
        class="absolute top-full right-0 mt-2 w-80 bg-white dark:bg-gray-800 rounded-xl shadow-lg ring-1 ring-black ring-opacity-5 z-50 overflow-hidden">

        <div class="flex justify-between items-center px-4 py-3 border-b border-gray-100 dark:border-gray-700">
            <span class="font-semibold text-sm text-gray-700 dark:text-gray-200">Notificações</span>
            @if($unreadCount > 0)
                <form action="{{ route('notifications.markAllRead') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs text-red-700 dark:text-red-400 hover:underline">
                        Marcar todas como lidas
                    </button>
                </form>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
            @forelse($unreadNotifications as $notification)
                @php
                    $data = $notification->data;
                    $icon = match($data['type'] ?? '') {
                        'aviso_reminder' => '🔔',
                        'aviso_expiring' => '⏰',
                        default => '📢',
                    };
                @endphp
                <a href="{{ route('notifications.markRead', $notification->id) }}"
                   class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition text-sm">
                    <span class="text-lg flex-shrink-0 mt-0.5">{{ $icon }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800 dark:text-gray-200 truncate">{{ $data['title'] ?? '' }}</p>
                        <p class="text-gray-500 dark:text-gray-400 text-xs">{{ $data['message'] ?? '' }}</p>
                        <p class="text-gray-400 dark:text-gray-500 text-xs mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                </a>
            @empty
                <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    Nenhuma notificação nova
                </div>
            @endforelse
        </div>

        <div class="px-4 py-2 border-t border-gray-100 dark:border-gray-700 text-center">
            <a href="{{ route('avisos.index') }}" class="text-xs text-red-700 dark:text-red-400 hover:underline">
                Ver todos os avisos
            </a>
        </div>
    </div>
</div>

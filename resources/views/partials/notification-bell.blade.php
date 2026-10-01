{{--
    Sino de notificações.

    placement: 'bar'      — na barra de cima das Módulos, abre para baixo;
               'floating' — no canto da tela (menus de antes), abre para cima.

    A lista vem do layout ($unreadNotifications), que consulta uma vez só para
    os dois sinos.
--}}
@php
    $placement = $placement ?? 'floating';
    $unreadNotifications ??= auth()->user()->unreadNotifications()->latest()->limit(8)->get();
    $unreadCount = $unreadNotifications->count();
    $badge = $unreadCount > 9 ? '9+' : $unreadCount;
@endphp

<div x-data="{ bellOpen: false }" @keydown.escape.window="bellOpen = false"
    class="{{ $placement === 'bar' ? 'relative' : 'fixed bottom-6 right-6 z-50 flex flex-col items-end' }}">

    @if ($placement === 'bar')
        <button type="button" @click="bellOpen = !bellOpen" :aria-expanded="bellOpen.toString()" aria-expanded="false"
            aria-label="Notificações{{ $unreadCount ? ' (' . $unreadCount . ' novas)' : '' }}"
            class="relative grid h-[38px] w-[38px] place-items-center rounded-full text-ink-2 transition hover:bg-subtle hover:text-ink">
            <x-icon name="bell" class="h-5 w-5" />
            @if ($unreadCount > 0)
                <span class="absolute right-[3px] top-[3px] h-4 min-w-4 rounded-full bg-grena px-1 text-center text-[10px] font-bold leading-4 text-white ring-2 ring-surface">{{ $badge }}</span>
            @endif
        </button>
    @endif

    <div x-show="bellOpen" x-cloak x-transition.opacity.duration.150ms @click.outside="bellOpen = false"
        class="{{ $placement === 'bar' ? 'absolute right-0 top-full mt-2' : 'mb-3' }} z-50 w-[min(22rem,calc(100vw-24px))] overflow-hidden rounded-[20px] border border-line bg-surface text-ink shadow-pop">

        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-sm font-bold">Notificações</span>
            @if ($unreadCount > 0)
                <form action="{{ route('notifications.markAllRead') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-xs font-bold text-grena-ink hover:underline">Marcar todas como lidas</button>
                </form>
            @endif
        </div>

        <div class="max-h-80 overflow-y-auto px-2 pb-2">
            @forelse ($unreadNotifications as $notification)
                @php
                    $data = $notification->data;
                    $glyph = match ($data['type'] ?? '') {
                        'aviso_expiring' => 'clock',
                        default => 'bell',
                    };
                @endphp
                <a href="{{ route('notifications.markRead', $notification->id) }}"
                    class="flex items-start gap-3 rounded-xl px-2.5 py-2.5 text-sm text-ink no-underline transition hover:bg-subtle">
                    <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px]" style="{{ \App\View\AreaColor::style('info') }}">
                        <x-icon :name="$glyph" class="h-4 w-4" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <b class="block truncate font-semibold">{{ $data['title'] ?? '' }}</b>
                        <span class="block text-xs text-ink-2">{{ $data['message'] ?? '' }}</span>
                        <span class="mt-0.5 block text-xs text-ink-3">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-3">Nenhuma notificação nova</p>
            @endforelse
        </div>

        <div class="border-t border-line px-4 py-2.5 text-center">
            <a href="{{ route('avisos.index') }}" class="text-xs font-bold text-grena-ink hover:underline">Ver todos os avisos</a>
        </div>
    </div>

    @if ($placement === 'floating')
        <button type="button" @click="bellOpen = !bellOpen" aria-label="Notificações"
            class="relative grid h-14 w-14 place-items-center rounded-full bg-grena text-white shadow-pop transition hover:bg-grena-hover focus:outline-none focus:ring-4 focus:ring-grena-tint">
            <x-icon name="bell" class="h-6 w-6" />
            @if ($unreadCount > 0)
                <span class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full border-2 border-grena bg-white px-1 text-xs font-bold leading-none text-grena">{{ $badge }}</span>
            @endif
        </button>
    @endif
</div>

{{--
    Itens do menu da conta.

    Existe para os três dropdowns (lateral expandida, lateral recolhida e barra
    superior) não saírem de sincronia — eram três cópias do mesmo bloco, e
    agora há um item a mais para manter alinhado.

    Depende de estar dentro de um x-data com `userOpen` (os três têm) e do
    escopo do laraShell, de onde vem `organizerOpen`.
--}}
@php
    $itemClasses = 'block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition';
@endphp

<a href="{{ route('profile.edit') }}" class="{{ $itemClasses }}">Perfil</a>
<a href="{{ route('docs.index') }}" class="{{ $itemClasses }}">Documentação</a>
@role('admin')
<a href="{{ route('users.index') }}" class="{{ $itemClasses }}">Usuários</a>
@endrole

<button type="button" @click="organizerOpen = true; userOpen = false"
    class="{{ $itemClasses }} flex w-full items-center gap-2 text-left">
    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7M17 20l3-3-3-3" />
    </svg>
    Organizar menu
</button>

<x-nav-mode-toggle />

<form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 dark:border-gray-700">
    @csrf
    <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20">Sair</button>
</form>

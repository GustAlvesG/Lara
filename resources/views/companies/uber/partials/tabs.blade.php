@php $active = $active ?? 'requests'; @endphp
@php
    $tabClass = fn (bool $on) => $on
        ? 'bg-grena text-white shadow-card'
        : 'bg-surface border border-line text-ink-2 hover:bg-subtle';
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    <a href="{{ route('company.uber.requests') }}"
       class="px-4 py-2 rounded-xl font-bold text-sm transition {{ $tabClass($active === 'requests') }}">
        Pedidos
    </a>
    <a href="{{ route('company.uber.waiting') }}"
       class="px-4 py-2 rounded-xl font-bold text-sm transition {{ $tabClass($active === 'waiting') }}">
        Aguardando Motorista
    </a>
    <a href="{{ route('company.uber.accesses') }}"
       class="px-4 py-2 rounded-xl font-bold text-sm transition {{ $tabClass($active === 'accesses') }}">
        Acessos Realizados
    </a>
</div>

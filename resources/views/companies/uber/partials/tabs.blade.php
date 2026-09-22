@php $active = $active ?? 'requests'; @endphp
@php
    $tabClass = fn (bool $on) => $on
        ? 'bg-indigo-600 text-white shadow-md'
        : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700';
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

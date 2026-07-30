@php $active = $active ?? 'requests'; @endphp
<div class="flex gap-2 mb-6">
    <a href="{{ route('company.uber.requests') }}"
       class="px-4 py-2 rounded-xl font-bold text-sm transition {{ $active === 'requests' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
        Pedidos
    </a>
    <a href="{{ route('company.uber.accesses') }}"
       class="px-4 py-2 rounded-xl font-bold text-sm transition {{ $active === 'accesses' ? 'bg-indigo-600 text-white shadow-md' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
        Acessos Realizados
    </a>
</div>

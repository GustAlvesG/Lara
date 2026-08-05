@php
    /** Selo do estado do cachê — a mesma leitura de EmployeeCache::statusLabel(). */
    $label = $cache->statusLabel();

    $color = match (true) {
        $cache->isPaid() => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        $cache->isCancelled(), $cache->isRecheckRejected(), $cache->isManagerRejected()
            => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        $cache->isPayable() => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
        $cache->awaitsCoordinatorRecheck(), $cache->awaitsManagerRecheck()
            => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        $cache->isManagerApproved() => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
        default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
@endphp

<span class="inline-flex px-2 py-1 rounded-full text-xs font-bold whitespace-nowrap {{ $color }}">{{ $label }}</span>

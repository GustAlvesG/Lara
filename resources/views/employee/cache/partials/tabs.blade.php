{{--
    Abas do Cachê: Cachês, Solicitar, Solicitações, Aprovação, Reconferência e
    Financeiro.

    Cada aba aparece só para quem a rota deixa entrar — a condição aqui é a
    mesma do controller, senão a aba viraria um link para um 403.

    Aceita `$activeTab` (nome de rota) para telas que não são a aba em si — a de
    um lote, por exemplo, que continua destacando "Solicitações".
--}}
@php
    use App\Support\EmployeeScope;

    $user = auth()->user();
    $access = EmployeeScope::for($user);
    $isCoordinator = EmployeeScope::isCoordinator($access);
    $isManager = $user?->isManagementCoordinator() ?? false;

    $tabs = [];

    if ($access['type'] !== 'none') {
        $tabs[] = ['route' => 'employee-caches.index', 'label' => 'Cachês', 'matches' => ['employee-caches.index']];
    }

    if ($isCoordinator) {
        $tabs[] = ['route' => 'employee-caches.create', 'label' => 'Solicitar', 'matches' => ['employee-caches.create']];
        $tabs[] = ['route' => 'employee-caches.batches', 'label' => 'Solicitações', 'matches' => ['employee-caches.batches', 'employee-caches.batches.show']];
    }

    if ($isManager) {
        $tabs[] = ['route' => 'employee-caches.queue', 'label' => 'Aprovação', 'matches' => ['employee-caches.queue']];
    }

    // A reconferência é do coordenador (1ª instância) e da gerência (2ª).
    if ($isCoordinator) {
        $tabs[] = ['route' => 'employee-caches.recheck.queue', 'label' => 'Reconferência', 'matches' => ['employee-caches.recheck.queue']];
    }

    if ($user?->can('manage-employee-cache-payments')) {
        $tabs[] = ['route' => 'employee-caches.finance', 'label' => 'Financeiro', 'matches' => ['employee-caches.finance']];
    }

    $activeTab = $activeTab ?? null;
@endphp

@if(count($tabs) > 1)
<div class="mb-6 border-b border-gray-200 dark:border-gray-700">
    <nav class="-mb-px flex gap-6 overflow-x-auto">
        @foreach($tabs as $tab)
            @php
                $active = $activeTab ? $activeTab === $tab['route'] : request()->routeIs($tab['matches']);
            @endphp
            <a href="{{ route($tab['route']) }}"
               @if($active) aria-current="page" @endif
               class="whitespace-nowrap border-b-2 px-1 py-3 text-sm font-bold transition
                      {{ $active
                          ? 'border-[#A00001] text-[#A00001] dark:text-red-400 dark:border-red-400'
                          : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:border-gray-300' }}">
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
@endif

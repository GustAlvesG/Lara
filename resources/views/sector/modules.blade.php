{{--
    Permissões por módulo (só leitura): para cada módulo do catálogo, os setores
    e o que cada um alcança nele, os de acesso total e as pessoas com permissão
    individual dada ou negada. Mudar é na tela do setor ou na de Usuários.
--}}
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Permissões por módulo" :back="route('sectors.index')">
            Quais setores alcançam o quê em cada módulo. Para mudar, abra o setor (ou a pessoa, em Usuários).
        </x-page-title>

        @include('partials.alerts')

        @if($fullAccess->isNotEmpty())
            <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-ink-2">
                <x-pill kind="info">Acesso total</x-pill>
                <span>
                    @foreach($fullAccess as $sector)
                        <a href="{{ route('sectors.show', $sector->id) }}" class="font-semibold text-grena-ink hover:underline">{{ $sector->name }}</a>@if(! $loop->last), @endif
                    @endforeach
                    alcançam tudo de todos os módulos, menos o que for negado a uma pessoa.
                </span>
            </div>
        @endif

        <x-search-bar mode="client" target="#modulos" placeholder="Buscar módulo, setor, permissão ou pessoa" class="mb-4" />

        <div class="grid grid-cols-1 gap-4" id="modulos">
            @foreach($modules as $module)
                <article data-search="{{ $module['name'] }}" class="rounded-card bg-surface shadow-card overflow-hidden">
                    <header class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line bg-subtle px-5 py-3">
                        <h2 class="font-display text-base font-semibold tracking-tight text-ink">{{ $module['name'] }}</h2>
                        <span class="text-xs text-ink-3">{{ $module['count'] }} {{ $module['count'] == 1 ? 'permissão' : 'permissões' }} no catálogo</span>
                    </header>

                    @if(! $module['rows'] && ! $module['individuals'])
                        <p class="px-5 py-4 text-sm text-ink-2">Nenhum setor alcança este módulo{{ $fullAccess->isNotEmpty() ? ' — só os de acesso total' : '' }}.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <tbody class="divide-y divide-line">
                                    @foreach($module['rows'] as $row)
                                        <tr class="align-top">
                                            <th scope="row" class="w-1/4 min-w-[9rem] px-5 py-3 text-left font-semibold">
                                                <a href="{{ route('sectors.show', $row['sector']->id) }}" class="text-ink hover:text-grena-ink hover:underline">{{ $row['sector']->name }}</a>
                                            </th>
                                            <td class="px-5 py-3 text-ink">
                                                @foreach($row['grants'] as $grant)
                                                    <span>{{ $grant['label'] }}@if($grant['coordinators']) <span class="text-xs font-bold text-warn" title="Só coordenadores">(só coordenadores)</span>@endif</span>@if(! $loop->last)<span class="text-ink-3">, </span>@endif
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach

                                    @foreach($module['individuals'] as $item)
                                        <tr class="align-top bg-subtle/40">
                                            <th scope="row" class="w-1/4 min-w-[9rem] px-5 py-3 text-left font-semibold">
                                                <a href="{{ route('users.edit', $item['user']->id) }}" class="text-ink hover:text-grena-ink hover:underline">{{ $item['user']->name }}</a>
                                                <span class="block text-xs font-normal text-ink-3">individual</span>
                                            </th>
                                            <td class="px-5 py-3 text-ink space-y-1">
                                                @if($item['granted'])
                                                    <p><span class="text-xs font-bold text-ok">Dada:</span> {{ implode(', ', $item['granted']) }}</p>
                                                @endif
                                                @if($item['denied'])
                                                    <p><span class="text-xs font-bold text-danger">Negada:</span> <span class="line-through">{{ implode(', ', $item['denied']) }}</span></p>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </x-page>
</x-app-layout>

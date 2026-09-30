{{--
    Setores de um usuário: uma linha por setor, com Fora / Colaborador /
    Coordenador. O formulário manda TODAS as linhas — o vazio quer dizer "fora
    do setor", e é assim que o UserController sabe o que tirar.

    Espera: $sectors (todos) e $current (sector_id => role).
--}}
@php $current = $current ?? []; @endphp

<div class="divide-y divide-gray-100 dark:divide-gray-700">
    @foreach($sectors as $sector)
        @php $role = old('sectors.' . $sector->id, $current[$sector->id] ?? ''); @endphp
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
            <div>
                <p class="font-semibold text-gray-900 dark:text-white">
                    {{ $sector->name }}
                    @if($sector->full_access)
                        <span class="ml-1 px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-300">Acesso total</span>
                    @endif
                </p>
                @if($sector->description)
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $sector->description }}</p>
                @endif
            </div>
            <select name="sectors[{{ $sector->id }}]"
                class="w-full sm:w-48 px-3 py-2 border border-gray-200 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 outline-none">
                <option value="" @selected($role === '')>Fora do setor</option>
                <option value="collaborator" @selected($role === 'collaborator')>Colaborador</option>
                <option value="coordinator" @selected($role === 'coordinator')>Coordenador</option>
            </select>
        </div>
    @endforeach
</div>

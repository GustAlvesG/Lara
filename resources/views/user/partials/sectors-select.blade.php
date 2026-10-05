{{--
    Setores de um usuário: uma linha por setor, com Fora / Colaborador /
    Coordenador. O formulário manda TODAS as linhas — o vazio quer dizer "fora
    do setor", e é assim que o UserController sabe o que tirar.

    Espera: $sectors (todos) e $current (sector_id => role).
--}}
@php $current = $current ?? []; @endphp

<div class="divide-y divide-line">
    @foreach($sectors as $sector)
        @php $role = old('sectors.' . $sector->id, $current[$sector->id] ?? ''); @endphp
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
            <div>
                <p class="font-semibold text-ink">
                    {{ $sector->name }}
                    @if($sector->full_access)
                        <span class="ml-1 px-2 py-0.5 text-[10px] font-bold uppercase rounded bg-danger-soft text-danger">Acesso total</span>
                    @endif
                </p>
                @if($sector->description)
                    <p class="text-xs text-ink-2">{{ $sector->description }}</p>
                @endif
            </div>
            <select name="sectors[{{ $sector->id }}]"
                class="w-full sm:w-48 px-3 py-2 border border-line rounded-lg text-sm bg-surface text-ink focus:ring-2 focus:ring-grena-tint outline-none">
                <option value="" @selected($role === '')>Fora do setor</option>
                <option value="collaborator" @selected($role === 'collaborator')>Colaborador</option>
                <option value="coordinator" @selected($role === 'coordinator')>Coordenador</option>
            </select>
        </div>
    @endforeach
</div>

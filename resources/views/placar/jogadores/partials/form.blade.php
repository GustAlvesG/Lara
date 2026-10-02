@php
    $jogador = $jogador ?? null;
@endphp

<div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
    <div class="p-6 border-b border-line bg-subtle">
        <h2 class="text-lg font-bold text-ink">Dados do Jogador</h2>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
        {{--
            Equipe e modalidade definem em quais times o jogador pode entrar:
            ele pertence a uma só de cada, e pode estar em vários times
            daquela equipe (Sub-15 e Adulto, por exemplo). Trocar depois só é
            possível enquanto ele não estiver em elenco nenhum — senão os
            vínculos existentes ficariam inválidos.
        --}}
        @php $temElenco = $jogador?->elencos()->exists() ?? false; @endphp

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Equipe <span class="text-danger">*</span></label>
            <select name="equipe_id" required @disabled($temElenco)
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink disabled:opacity-60">
                <option value="">Selecione...</option>
                @foreach($equipes as $equipeOpcao)
                    <option value="{{ $equipeOpcao->id }}" @selected((int) old('equipe_id', $jogador?->equipe_id) === $equipeOpcao->id)>{{ $equipeOpcao->nome }}</option>
                @endforeach
            </select>
            @error('equipe_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Modalidade <span class="text-danger">*</span></label>
            <select name="modalidade_id" required @disabled($temElenco)
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink disabled:opacity-60">
                <option value="">Selecione...</option>
                @foreach($modalidades as $modalidadeOpcao)
                    <option value="{{ $modalidadeOpcao->id }}" @selected((int) old('modalidade_id', $jogador?->modalidade_id) === $modalidadeOpcao->id)>{{ $modalidadeOpcao->nome }}</option>
                @endforeach
            </select>
            @error('modalidade_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        @if($temElenco)
            {{-- Campo desabilitado não é enviado no POST; sem isto o update
                 receberia equipe/modalidade vazias e falharia na validação. --}}
            <input type="hidden" name="equipe_id" value="{{ $jogador->equipe_id }}">
            <input type="hidden" name="modalidade_id" value="{{ $jogador->modalidade_id }}">
            <p class="md:col-span-2 -mt-3 text-xs text-ink-2">
                Equipe e modalidade estão travadas porque o jogador já está em elenco.
                Remova-o dos elencos para poder trocá-las.
            </p>
        @endif

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-ink mb-1">Nome completo <span class="text-danger">*</span></label>
            <input type="text" name="nome" value="{{ old('nome', $jogador?->nome) }}" required
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink">
            @error('nome')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Nome de exibição</label>
            <input type="text" name="nome_exibicao" value="{{ old('nome_exibicao', $jogador?->nome_exibicao) }}"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink">
            <p class="mt-1 text-xs text-ink-3">Nome curto exibido no telão — se vazio, usa o completo.</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-ink mb-1">Data de nascimento</label>
            <input type="date" name="data_nascimento" value="{{ old('data_nascimento', $jogador?->data_nascimento?->toDateString()) }}"
                class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-ok-soft outline-none transition bg-surface text-ink">
            <p class="mt-1 text-xs text-ink-3">
                @if($jogador?->idade() !== null)
                    {{ $jogador->idade() }} anos — é o que a montagem do elenco mostra para conferir a categoria.
                @else
                    Usada para mostrar a idade ao montar o elenco.
                @endif
            </p>
            @error('data_nascimento')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
        </div>

        @if($jogador)
        <div class="md:col-span-2">
            <label class="flex items-center gap-3 p-4 rounded-xl border border-line cursor-pointer hover:bg-subtle transition">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $jogador->ativo))
                    class="w-5 h-5 rounded border-line-strong text-ok focus:ring-ok-soft">
                <span class="text-sm font-bold text-ink">Ativo</span>
            </label>
        </div>
        @endif
    </div>
</div>

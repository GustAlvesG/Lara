@php
    $jogador = $jogador ?? null;
@endphp

<div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
    <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/50">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white">Dados do Jogador</h2>
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
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Equipe <span class="text-red-500">*</span></label>
            <select name="equipe_id" required @disabled($temElenco)
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60">
                <option value="">Selecione...</option>
                @foreach($equipes as $equipeOpcao)
                    <option value="{{ $equipeOpcao->id }}" @selected((int) old('equipe_id', $jogador?->equipe_id) === $equipeOpcao->id)>{{ $equipeOpcao->nome }}</option>
                @endforeach
            </select>
            @error('equipe_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Modalidade <span class="text-red-500">*</span></label>
            <select name="modalidade_id" required @disabled($temElenco)
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white disabled:opacity-60">
                <option value="">Selecione...</option>
                @foreach($modalidades as $modalidadeOpcao)
                    <option value="{{ $modalidadeOpcao->id }}" @selected((int) old('modalidade_id', $jogador?->modalidade_id) === $modalidadeOpcao->id)>{{ $modalidadeOpcao->nome }}</option>
                @endforeach
            </select>
            @error('modalidade_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        @if($temElenco)
            {{-- Campo desabilitado não é enviado no POST; sem isto o update
                 receberia equipe/modalidade vazias e falharia na validação. --}}
            <input type="hidden" name="equipe_id" value="{{ $jogador->equipe_id }}">
            <input type="hidden" name="modalidade_id" value="{{ $jogador->modalidade_id }}">
            <p class="md:col-span-2 -mt-3 text-xs text-gray-500 dark:text-gray-400">
                Equipe e modalidade estão travadas porque o jogador já está em elenco.
                Remova-o dos elencos para poder trocá-las.
            </p>
        @endif

        <div class="md:col-span-2">
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome completo <span class="text-red-500">*</span></label>
            <input type="text" name="nome" value="{{ old('nome', $jogador?->nome) }}" required
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            @error('nome')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Nome de exibição</label>
            <input type="text" name="nome_exibicao" value="{{ old('nome_exibicao', $jogador?->nome_exibicao) }}"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Nome curto exibido no telão — se vazio, usa o completo.</p>
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Documento</label>
            <input type="text" name="documento" value="{{ old('documento', $jogador?->documento) }}"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-1">Data de nascimento</label>
            <input type="date" name="data_nascimento" value="{{ old('data_nascimento', $jogador?->data_nascimento?->toDateString()) }}"
                class="w-full px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-emerald-500 outline-none transition bg-white dark:bg-gray-900 text-gray-900 dark:text-white">
        </div>

        @if($jogador)
        <div class="md:col-span-2">
            <label class="flex items-center gap-3 p-4 rounded-xl border border-gray-200 dark:border-gray-600 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $jogador->ativo))
                    class="w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                <span class="text-sm font-bold text-gray-700 dark:text-gray-300">Ativo</span>
            </label>
        </div>
        @endif
    </div>
</div>

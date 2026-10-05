<x-app-layout>
<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-8">

        @include('partials.alerts')

        <div class="mb-2 flex items-center gap-4">
            <a href="{{ route('placar.times.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-ok border border-line transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">
                    {{ $time->nomeExibicaoResolvido() }}
                    @if($time->criado_em_campo)
                        <span class="ml-2 align-middle inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-warn-soft text-warn">Criado em campo</span>
                    @endif
                </h1>
                <p class="text-ink-2 font-medium">{{ $time->equipe->nome }} · {{ $time->modalidade->nome }}</p>
                @can('placar.scout')
                    <a href="{{ route('placar.scout.time', $time) }}" class="text-sm font-bold text-grena-ink hover:underline">Ver painel no scout →</a>
                @endcan
            </div>
        </div>

        {{-- Logo --}}
        <div class="bg-surface rounded-2xl shadow-pop border border-line p-6">
            <h2 class="text-lg font-bold text-ink mb-4">Logo</h2>
            <div class="flex items-center gap-6">
                @if($time->logoUrl())
                    <img src="{{ $time->logoUrl() }}" class="w-24 h-24 rounded-xl object-cover border border-line">
                @else
                    <div class="w-24 h-24 rounded-xl bg-subtle flex items-center justify-center text-xs text-ink-3 text-center px-2">herda da equipe, se houver</div>
                @endif
                <div class="flex flex-col gap-2">
                    <form action="{{ route('placar.times.logo.store', $time) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
                        @csrf
                        <input type="file" name="arquivo" accept="image/jpeg,image/png,image/webp" required class="text-sm">
                        <button type="submit" class="px-4 py-2 bg-grena text-white rounded-full text-sm font-bold hover:bg-grena-hover transition">Enviar</button>
                    </form>
                    @if($time->logo_path)
                    <form action="{{ route('placar.times.logo.destroy', $time) }}" method="POST" onsubmit="return confirm('Remover a logo própria?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-danger hover:underline">Remover logo própria</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>

        {{-- Dados --}}
        <form action="{{ route('placar.times.update', $time) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados do Time</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Categoria <span class="text-danger">*</span></label>
                        <input type="text" name="categoria" value="{{ old('categoria', $time->categoria) }}" required
                            list="categorias-existentes" autocomplete="off"
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @include('placar.times.partials.categorias-datalist')
                        @error('categoria')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Nome de exibição</label>
                        <input type="text" name="nome_exibicao" value="{{ old('nome_exibicao', $time->nome_exibicao) }}"
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    </div>
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 p-4 rounded-xl border border-line cursor-pointer hover:bg-subtle transition">
                            <input type="hidden" name="ativo" value="0">
                            <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $time->ativo))
                                class="w-5 h-5 rounded border-line-strong text-ok focus:ring-ok-soft">
                            <span class="text-sm font-bold text-ink">Ativo</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">Salvar Alterações</button>
            </div>
        </form>

        {{-- Elenco --}}
        <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
            <div class="p-6 border-b border-line flex items-center justify-between">
                <h2 class="text-lg font-bold text-ink">Elenco {{ $temporada }}</h2>
                @if($temporadaAnteriorTemElenco)
                <form action="{{ route('placar.times.elenco.copiar', $time) }}" method="POST"
                      onsubmit="return confirm('Copiar o elenco de {{ $temporada - 1 }} para {{ $temporada }}?')">
                    @csrf
                    <button type="submit" class="text-sm font-bold text-grena-ink hover:underline">
                        Copiar elenco de {{ $temporada - 1 }}
                    </button>
                </form>
                @endif
            </div>

            @if($elenco->isEmpty())
                <div class="p-6 text-sm text-ink-2">Nenhum jogador vinculado nesta temporada ainda.</div>
            @else
                <ul class="divide-y divide-line">
                    @foreach($elenco as $vinculo)
                    <li class="p-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            @if($vinculo->jogador->fotoUrl())
                                <img src="{{ $vinculo->jogador->fotoUrl() }}" class="w-10 h-10 rounded-full object-cover border border-line">
                            @else
                                <div class="w-10 h-10 rounded-full bg-subtle"></div>
                            @endif
                            <div>
                                <a href="{{ route('placar.jogadores.show', $vinculo->jogador) }}" class="text-sm font-semibold text-ink hover:underline">
                                    {{ $vinculo->jogador->nomeExibicaoResolvido() }}
                                </a>
                                <p class="text-xs text-ink-3">
                                    nº {{ $vinculo->numero ?? '—' }}
                                    @if($vinculo->posicao) · {{ $vinculo->posicao }} @endif
                                    @if($vinculo->jogador->idade() !== null) · {{ $vinculo->jogador->idade() }} anos @endif
                                </p>
                            </div>
                        </div>
                        <form action="{{ route('placar.times.elenco.destroy', [$time, $vinculo]) }}" method="POST"
                              onsubmit="return confirm('Remover do elenco?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-bold text-danger hover:underline">Remover</button>
                        </form>
                    </li>
                    @endforeach
                </ul>
            @endif

            {{-- Adicionar jogadores: marca vários e salva de uma vez --}}
            <div class="p-6 border-t border-line bg-subtle">
                @if($jogadoresDisponiveis->isEmpty())
                    <h3 class="text-sm font-bold text-ink mb-3">Adicionar ao elenco</h3>
                    <p class="text-sm text-ink-2">
                        Não há jogador disponível de {{ $time->equipe->nome }} / {{ $time->modalidade->nome }} fora deste elenco.
                        <a href="{{ route('placar.jogadores.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar um novo</a>
                        ou importar por planilha.
                    </p>
                @else
                @php
                    // [id, texto pesquisável] — alimenta o filtro e o "marcar todos".
                    $indice = $jogadoresDisponiveis->map(fn ($j) => [
                        (string) $j->id,
                        trim($j->nomeExibicaoResolvido() . ' ' . $j->nome),
                    ])->values();
                @endphp

                <div x-data="{
                        busca: '',
                        marcados: {},
                        numeros: {},
                        posicoes: {},
                        emUso: {{ Illuminate\Support\Js::from($numerosEmUso) }},
                        indice: {{ Illuminate\Support\Js::from($indice) }},

                        combina(texto) {
                            const termo = this.busca.trim().toLowerCase();
                            return termo === '' || texto.toLowerCase().includes(termo);
                        },
                        get total() {
                            return Object.values(this.marcados).filter(Boolean).length;
                        },
                        get nenhumVisivel() {
                            return !this.indice.some(([, nome]) => this.combina(nome));
                        },
                        /* Menor camisa livre, contando as já no elenco e as
                           que estão sendo atribuídas agora nesta mesma tela. */
                        proximoNumero() {
                            const ocupados = new Set([
                                ...this.emUso.map(String),
                                ...Object.entries(this.numeros)
                                    .filter(([id]) => this.marcados[id])
                                    .map(([, n]) => String(n))
                                    .filter(n => n !== ''),
                            ]);
                            let n = 1;
                            while (ocupados.has(String(n))) n++;
                            return String(n);
                        },
                        /* Sugere a camisa ao marcar, sem sobrescrever o que a
                           pessoa já tiver digitado. */
                        sugerir(id) {
                            if (this.marcados[id] && !this.numeros[id]) {
                                this.numeros[id] = this.proximoNumero();
                            }
                        },
                        marcarVisiveis() {
                            this.indice.forEach(([id, nome]) => {
                                if (this.combina(nome) && !this.marcados[id]) {
                                    this.marcados[id] = true;
                                    this.sugerir(id);
                                }
                            });
                        },
                        limpar() {
                            this.marcados = {};
                        },
                     }">

                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                        <h3 class="text-sm font-bold text-ink">
                            Adicionar ao elenco
                            <span class="font-normal text-ink-3">({{ $jogadoresDisponiveis->count() }} disponíve{{ $jogadoresDisponiveis->count() === 1 ? 'l' : 'is' }})</span>
                        </h3>
                        <div class="flex items-center gap-3 text-xs font-bold">
                            <button type="button" @click="marcarVisiveis()"
                                    class="text-grena-ink hover:underline">Marcar todos</button>
                            <button type="button" @click="limpar()" x-show="total > 0" x-cloak
                                    class="text-ink-2 hover:underline">Limpar seleção</button>
                        </div>
                    </div>

                    <input type="text" x-model="busca" placeholder="Filtrar por nome — filtra enquanto você digita"
                        class="w-full mb-4 px-4 py-2 border border-line rounded-lg bg-surface text-ink text-sm">

                    <form action="{{ route('placar.times.elenco.store', $time) }}" method="POST">
                        @csrf

                        <div class="max-h-96 overflow-y-auto rounded-xl border border-line bg-surface divide-y divide-line">
                            @foreach($jogadoresDisponiveis as $jogador)
                            @php $busca = trim($jogador->nomeExibicaoResolvido() . ' ' . $jogador->nome); @endphp
                            <label x-show="combina(@js($busca))"
                                   class="flex items-center gap-3 p-3 cursor-pointer hover:bg-subtle transition"
                                   :class="marcados[{{ $jogador->id }}] && 'bg-ok-soft'">

                                {{-- x-model (e não :checked + click.prevent): o
                                     checkbox precisa alternar nativamente, senão
                                     não é enviado no POST. --}}
                                <input type="checkbox" name="jogadores[{{ $jogador->id }}][selecionado]" value="1"
                                       x-model="marcados[{{ $jogador->id }}]"
                                       @change="sugerir({{ $jogador->id }})"
                                       class="w-5 h-5 rounded border-line-strong text-ok focus:ring-ok-soft shrink-0">

                                @if($jogador->fotoUrl())
                                    <img src="{{ $jogador->fotoUrl() }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-subtle shrink-0"></div>
                                @endif

                                {{-- A idade fica ao lado do nome porque é por
                                     ela que se confere se o jogador cabe na
                                     categoria do time (Sub-15, Sub-17…). --}}
                                <span class="flex-1 min-w-0">
                                    <span class="block text-sm text-ink truncate">{{ $jogador->nomeExibicaoResolvido() }}</span>
                                    @if($jogador->idade() !== null)
                                        <span class="block text-xs text-ink-2 truncate">
                                            {{ $jogador->idade() }} anos
                                            <span class="text-ink-3">· {{ $jogador->data_nascimento->format('d/m/Y') }}</span>
                                        </span>
                                    @else
                                        <span class="block text-xs text-ink-3 truncate">idade não informada</span>
                                    @endif
                                </span>

                                {{-- Só habilitados quando marcado: campo editável
                                     em linha não selecionada não seria enviado. --}}
                                <input type="text" name="jogadores[{{ $jogador->id }}][numero]" placeholder="nº"
                                       x-model="numeros[{{ $jogador->id }}]"
                                       :disabled="!marcados[{{ $jogador->id }}]"
                                       @click.stop
                                       class="w-16 px-2 py-1 border border-line rounded-lg bg-surface text-ink text-sm disabled:opacity-40 shrink-0">

                                <input type="text" name="jogadores[{{ $jogador->id }}][posicao]" placeholder="posição"
                                       x-model="posicoes[{{ $jogador->id }}]"
                                       :disabled="!marcados[{{ $jogador->id }}]"
                                       @click.stop
                                       class="w-28 px-2 py-1 border border-line rounded-lg bg-surface text-ink text-sm disabled:opacity-40 shrink-0">
                            </label>
                            @endforeach

                            <p x-show="nenhumVisivel" x-cloak
                               class="p-4 text-sm text-ink-2">
                                Nenhum jogador disponível com esse filtro.
                            </p>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-4">
                            <p class="text-xs text-ink-2" x-show="total > 0" x-cloak>
                                <span x-text="total"></span> selecionado(s) — a camisa é sugerida automaticamente e pode ser trocada.
                            </p>
                            <button type="submit" :disabled="total === 0"
                                class="ml-auto px-5 py-2 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                                <span x-show="total === 0">Adicionar ao elenco</span>
                                <span x-show="total > 0" x-cloak>Adicionar <span x-text="total"></span> ao elenco</span>
                            </button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
        </div>

        <div class="flex justify-end">
            <form method="POST" action="{{ route('placar.times.destroy', $time) }}"
                  onsubmit="return confirm('Excluir este time?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 text-sm font-bold text-danger hover:bg-danger-soft rounded-lg transition">
                    Excluir Time
                </button>
            </form>
        </div>
    </div>
</div>
</x-app-layout>

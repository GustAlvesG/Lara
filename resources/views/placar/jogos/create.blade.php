<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Novo Jogo') }}
        </h2>
    </x-slot>

<div class="py-6">
    <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        {{--
            Ordem de escolha: modalidade → categoria → times. As listas de
            times (e a de competições) mostram só o que combina com o que já
            foi escolhido; sem escolha, mostram tudo.

            O filtro é por <template x-if> em volta de cada <option>, e não
            x-show: `display:none` em <option> é ignorado pelo Safari e
            deixava o time de outra modalidade selecionável. Com x-if a opção
            sai do DOM, e se era a selecionada o select volta ao "Selecione"
            em vez de enviar um time que não está mais à vista.

            As opções continuam renderizadas no servidor (dentro do
            template), na ordem alfabética do controller.
        --}}
        <form
            action="{{ route('placar.jogos.store') }}" method="POST"
            x-data="{
                modalidadeId: @js((string) old('modalidade_id', '')),
                categoria: @js((string) old('categoria', '')),
                categorias: {{ Illuminate\Support\Js::from($categorias) }},
                categoriaExisteNaModalidade(c) {
                    return !this.modalidadeId || c.modalidades.map(String).includes(String(this.modalidadeId));
                },
                trocouModalidade() {
                    const atual = this.categorias.find(c => c.chave === this.categoria);
                    if (atual && !this.categoriaExisteNaModalidade(atual)) this.categoria = '';
                },
                timeVisivel(modalidadeId, chave) {
                    return (!this.modalidadeId || String(this.modalidadeId) === String(modalidadeId))
                        && (!this.categoria || this.categoria === chave);
                },
            }"
        >
            @csrf
            <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                <div class="p-6 border-b border-line bg-subtle">
                    <h2 class="text-lg font-bold text-ink">Dados do Jogo</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Modalidade <span class="text-danger">*</span></label>
                        <select name="modalidade_id" x-model="modalidadeId" @change="trocouModalidade()" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Selecione</option>
                            @foreach($modalidades as $modalidade)
                                <option value="{{ $modalidade->id }}" @selected((string) old('modalidade_id') === (string) $modalidade->id)>{{ $modalidade->nome }}</option>
                            @endforeach
                        </select>
                        @error('modalidade_id')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Categoria</label>
                        <select name="categoria" x-model="categoria" class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Todas</option>
                            @foreach($categorias as $categoriaOpcao)
                                <template x-if="categoriaExisteNaModalidade(categorias[{{ $loop->index }}])">
                                    <option value="{{ $categoriaOpcao['chave'] }}" @selected((string) old('categoria') === $categoriaOpcao['chave'])>{{ $categoriaOpcao['nome'] }}</option>
                                </template>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-3">Os dois times precisam ser da mesma categoria — escolha aqui para a lista mostrar só eles.</p>
                    </div>

                    @foreach(['time_casa_id' => 'Time da casa', 'time_fora_id' => 'Time visitante'] as $campo => $rotulo)
                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">{{ $rotulo }} <span class="text-danger">*</span></label>
                        <select name="{{ $campo }}" required class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Selecione</option>
                            @foreach($times as $time)
                                <template x-if="timeVisivel({{ $time->modalidade_id }}, @js(\App\Services\Placar\CategoriaService::chave($time->categoria)))">
                                    <option value="{{ $time->id }}" @selected((string) old($campo) === (string) $time->id)>{{ $time->rotuloParaEscolha() }}</option>
                                </template>
                            @endforeach
                        </select>
                        @error($campo)<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>
                    @endforeach

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Competição</label>
                        <select name="competicao_id" class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                            <option value="">Nenhuma</option>
                            @foreach($competicoes as $competicao)
                                <template x-if="!modalidadeId || String(modalidadeId) === '{{ $competicao->modalidade_id }}'">
                                    <option value="{{ $competicao->id }}" @selected((string) old('competicao_id') === (string) $competicao->id)>{{ $competicao->nome }} ({{ $competicao->temporada }})</option>
                                </template>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Data e hora <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="data_hora" value="{{ old('data_hora') }}" required
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                        @error('data_hora')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-ink mb-1">Local</label>
                        <input type="text" name="local" value="{{ old('local') }}"
                            class="w-full px-4 py-2 border border-line rounded-lg bg-surface text-ink">
                    </div>
                </div>
            </div>

            @include('placar.partials.botoes-cadastro', ['voltar' => route('placar.jogos.index')])
        </form>
    </div>
</div>
</x-app-layout>

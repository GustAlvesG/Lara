{{--
    Modelos de carteirinha: frente e verso lado a lado, no formato do cartão.

    A lista vem inteira (são poucos modelos), então a busca filtra na própria
    página. Imagem que sumiu do disco cai no substituto na cor da área, em vez
    do ícone de imagem quebrada.
--}}
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Modelos de carteirinha">
            Modelos usados para emitir carteirinhas em cartão PVC.

            <x-slot:actions>
                <x-primary-button-a href="{{ route('card-templates.create') }}">
                    <x-icon name="plus" /> Novo modelo
                </x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if ($templates->isEmpty())
            <x-empty-state icon="card">
                Nenhum modelo cadastrado.
                <a href="{{ route('card-templates.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar o primeiro modelo</a>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#modelos" placeholder="Buscar modelo pelo nome" />

            <div id="modelos" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($templates as $template)
                    <x-card :data-search="$template->name . ' ' . ($template->is_active ? 'ativo' : 'inativo')">
                        <x-slot:media>
                            <div class="grid grid-cols-2 gap-px bg-line">
                                @foreach (['Frente' => $template->frontImageUrl(), 'Verso' => $template->backImageUrl()] as $lado => $url)
                                    <figure class="bg-surface">
                                        <div class="relative grid aspect-[54/85.6] max-h-72 w-full place-items-center overflow-hidden"
                                             style="{{ \App\View\AreaColor::style('cartao') }}">
                                            <x-icon name="card" class="h-10 w-10 opacity-55" />
                                            <img src="{{ $url }}" alt="{{ $lado }} de {{ $template->name }}" loading="lazy"
                                                 class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                                        </div>
                                        <figcaption class="py-1 text-center text-[10px] font-bold uppercase tracking-wider text-ink-3">{{ $lado }}</figcaption>
                                    </figure>
                                @endforeach
                            </div>
                        </x-slot:media>

                        <div class="flex items-start justify-between gap-2">
                            <b class="text-[15.5px] font-bold leading-snug text-ink">{{ $template->name }}</b>
                            @if ($template->is_active)
                                <x-pill kind="ok">Ativo</x-pill>
                            @else
                                <x-pill kind="off">Inativo</x-pill>
                            @endif
                        </div>

                        <x-slot:footer>
                            <x-secondary-button-a size="sm" href="{{ route('card-templates.edit', $template) }}">
                                <x-icon name="pencil" /> Editar
                            </x-secondary-button-a>
                            <form method="POST" action="{{ route('card-templates.destroy', $template) }}"
                                  onsubmit="return confirm(@js('Excluir o modelo “' . $template->name . '” permanentemente?'))">
                                @csrf
                                @method('DELETE')
                                <x-danger-button size="sm"><x-icon name="trash" /> Excluir</x-danger-button>
                            </form>
                        </x-slot:footer>
                    </x-card>
                @endforeach
            </div>
        @endif
    </x-page>
</x-app-layout>

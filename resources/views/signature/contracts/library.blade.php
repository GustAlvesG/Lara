{{--
    Biblioteca de cláusulas e modelos de contrato, por setor. A cláusula
    inserida num contrato é copiada: mudar aqui não mexe em contrato feito.
    Modelos nascem de um contrato ("Salvar como modelo", na tela dele).
--}}
@php
    $f = 'w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink focus:border-grena focus:ring-2 focus:ring-grena-tint';
    $l = 'mb-1 block text-xs font-bold text-ink-2';
    $card = 'space-y-3 rounded-card bg-surface p-5 shadow-card';
    $h = 'font-display text-base font-semibold text-ink';
    $itens = $editing ? ($editing->items ?? []) : [['level' => 1, 'html' => '']];
@endphp
<x-app-layout :bootstrap-grid="false">
    @include('signature.contracts.partials.styles')

    <x-page>
        <x-page-title title="Biblioteca de cláusulas e modelos" :back="route('signature-contracts.index')">
            Cláusulas e modelos do seu setor, para reaproveitar em contratos novos.
        </x-page-title>

        @include('partials.alerts')

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-5">
            <div class="space-y-4 xl:col-span-3">
                <x-search-bar :filters="['setor']" placeholder="Título, categoria ou texto" />

                @forelse($clauses as $clausula)
                    <div class="{{ $card }}">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-ink">{{ $clausula->title }}</p>
                                <p class="text-xs text-ink-2">
                                    {{ $clausula->category ?: 'Sem categoria' }} · {{ $clausula->sector_name ?? '—' }}
                                    · por {{ $clausula->updated_by_name ?? $clausula->created_by_name ?? '—' }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <a href="{{ route('signature-contract-library.index', ['editar' => $clausula->id]) }}" class="text-xs font-bold text-grena-ink hover:underline">Editar</a>
                                <form method="POST" action="{{ route('signature-contract-library.destroy', $clausula) }}" onsubmit="return confirm('Remover esta cláusula da biblioteca?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-bold text-danger hover:underline">Remover</button>
                                </form>
                            </div>
                        </div>
                        <div class="ct-paper !p-4 text-sm">
                            @foreach($clausula->items ?? [] as $item)
                                <div @class(['ml-6' => ($item['level'] ?? 1) === 2])>{!! $item['html'] !!}</div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="doc">
                        Nenhuma cláusula na biblioteca{{ filled($busca) ? ' com essa busca' : '' }}. Salve uma pelo botão
                        "Salvar na biblioteca" no editor do contrato, ou escreva ao lado.
                    </x-empty-state>
                @endforelse

                {{ $clauses->links() }}

                <div class="{{ $card }}">
                    <p class="{{ $h }}">Modelos de contrato</p>
                    @forelse($models as $modelo)
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span><b>{{ $modelo->name }}</b> <span class="text-xs text-ink-2">· {{ $modelo->sector_name }} · {{ count($modelo->content['clauses'] ?? []) }} cláusulas</span></span>
                            <form method="POST" action="{{ route('signature-contract-library.models.destroy', $modelo) }}" onsubmit="return confirm('Remover o modelo?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-danger hover:underline">Remover</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-ink-2">Nenhum modelo. Abra um contrato pronto e use "Salvar como modelo do setor".</p>
                    @endforelse
                </div>
            </div>

            <aside class="xl:col-span-2">
                <form method="POST" id="libForm"
                      action="{{ $editing ? route('signature-contract-library.update', $editing) : route('signature-contract-library.store') }}"
                      class="{{ $card }} xl:sticky xl:top-20">
                    @csrf
                    @if($editing) @method('PUT') @endif
                    <p class="{{ $h }}">{{ $editing ? 'Editar cláusula' : 'Nova cláusula' }}</p>

                    @if($sectors->isEmpty())
                        <p class="text-sm text-ink-2">Você não está em nenhum setor: não há onde guardar a cláusula.</p>
                    @else
                        <div>
                            <label class="{{ $l }}">Setor</label>
                            <select name="sector_id" class="{{ $f }}">
                                @foreach($sectors as $sector)
                                    <option value="{{ $sector->id }}" @selected((int) old('sector_id', $editing?->sector_id) === $sector->id)>{{ $sector->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="{{ $l }}">Título</label>
                            <input type="text" name="title" maxlength="200" value="{{ old('title', $editing?->title) }}" class="{{ $f }}" required placeholder="Multa">
                        </div>
                        <div>
                            <label class="{{ $l }}">Categoria (opcional)</label>
                            <input type="text" name="category" maxlength="80" value="{{ old('category', $editing?->category) }}" class="{{ $f }}" placeholder="Pagamento, Garantia, Foro…">
                        </div>
                        <div class="space-y-2" data-lib-items>
                            @foreach($itens as $i => $item)
                                <div class="ct-row space-y-1" data-lib-item>
                                    <div class="flex items-center justify-between gap-2">
                                        <select name="items[{{ $i }}][level]" class="h-8 rounded-md border border-line bg-surface px-2 text-xs">
                                            <option value="1" @selected(($item['level'] ?? 1) === 1)>Item (1.1)</option>
                                            <option value="2" @selected(($item['level'] ?? 1) === 2)>Subitem (1.1.1)</option>
                                        </select>
                                        <button type="button" data-lib-remove class="text-xs font-bold text-danger hover:underline">Remover</button>
                                    </div>
                                    @include('signature.contracts.partials.rich', ['name' => 'items[' . $i . '][html]', 'value' => $item['html'] ?? '', 'refs' => false])
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-ink-3">Pode usar variáveis no texto, como [[contratada]] ou [[valor_global]]: elas precisam existir no contrato onde a cláusula entrar.</p>
                        @error('items')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                        <div class="flex flex-wrap justify-between gap-2">
                            <button type="button" data-lib-add class="rounded-full bg-subtle px-3 py-1.5 text-xs font-bold text-ink hover:bg-line">+ Item</button>
                            <div class="flex gap-2">
                                @if($editing)
                                    <x-secondary-button-a href="{{ route('signature-contract-library.index') }}" size="sm">Cancelar</x-secondary-button-a>
                                @endif
                                <x-primary-button size="sm">{{ $editing ? 'Salvar alterações' : 'Salvar na biblioteca' }}</x-primary-button>
                            </div>
                        </div>
                    @endif
                </form>
            </aside>
        </div>
    </x-page>

    <script>
        (function () {
            var form = document.getElementById('libForm');
            if (!form) return;
            var lista = form.querySelector('[data-lib-items]');
            if (!lista) return;

            document.addEventListener('mousedown', function (e) { if (e.target.closest('[data-cmd]')) e.preventDefault(); });
            form.addEventListener('click', function (e) {
                var cmd = e.target.closest('[data-cmd]');
                if (cmd) {
                    cmd.closest('.ct-editor').querySelector('.ct-rich').focus();
                    document.execCommand(cmd.dataset.cmd, false, null);
                    return;
                }
                if (e.target.closest('[data-lib-remove]')) {
                    if (lista.querySelectorAll('[data-lib-item]').length > 1) e.target.closest('[data-lib-item]').remove();
                    return;
                }
                if (e.target.closest('[data-lib-add]')) {
                    var base = lista.querySelector('[data-lib-item]');
                    var novo = base.cloneNode(true);
                    var k = 'n' + Date.now();
                    novo.querySelectorAll('[name]').forEach(function (el) { el.name = el.name.replace(/^items\[[^\]]+\]/, 'items[' + k + ']'); });
                    novo.querySelector('.ct-rich').innerHTML = '';
                    novo.querySelector('select').value = '1';
                    lista.appendChild(novo);
                }
            });
            // A biblioteca não tem variáveis próprias para oferecer: o select fica de fora.
            form.querySelectorAll('select[data-insert]').forEach(function (s) { s.remove(); });
            form.addEventListener('submit', function () {
                form.querySelectorAll('.ct-rich').forEach(function (ed) {
                    ed.parentNode.querySelector('[data-rich-source]').value = ed.innerHTML;
                });
            });
        })();
    </script>
</x-app-layout>

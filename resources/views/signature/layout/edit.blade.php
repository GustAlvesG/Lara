@php
    /**
     * Papel timbrado: o cabeçalho e o rodapé da empresa nos documentos
     * assinados. Um só para o módulo; salvar cria a versão seguinte (ver
     * SignatureLayout).
     */
    $campo = 'w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[900px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Cabeçalho e rodapé" :back="route('signature-templates.index')">
            O papel timbrado da empresa em todos os documentos assinados.
        </x-page-title>

        @include('partials.alerts')

        <div class="rounded-2xl border border-warn/40 bg-warn-soft p-5">
            <p class="text-sm text-warn font-semibold mb-1">
                Vale para os documentos congelados daqui em diante.
            </p>
            <p class="text-xs text-warn leading-relaxed">
                Um documento já congelado continua com o cabeçalho e o rodapé que tinha: o PDF assinado precisa
                sair igual ao que a pessoa leu.
            </p>
        </div>

        <form action="{{ route('signature-layout.update') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="bg-surface rounded-card shadow-card p-6 space-y-6">

                @foreach([
                    ['header', 'Cabeçalho', $header, 'Sem imagem, o documento sai com o cabeçalho padrão: o nome do clube e o título.'],
                    ['footer', 'Rodapé', $footer, 'Fica na base da página, abaixo do código de validação.'],
                ] as [$qual, $titulo, $imagem, $ajuda])
                    <div>
                        <label for="{{ $qual }}_image" class="block text-sm font-bold text-ink mb-1">
                            Imagem do {{ mb_strtolower($titulo) }}
                        </label>

                        @if($imagem)
                            <div class="rounded-xl border border-line bg-white p-3 mb-3">
                                <img src="{{ $imagem }}" alt="{{ $titulo }} atual" style="max-height: 120px; max-width: 100%;">
                            </div>
                            <label class="flex items-center gap-2 mb-3 text-xs text-ink">
                                <input type="hidden" name="remove_{{ $qual }}" value="0">
                                <input type="checkbox" name="remove_{{ $qual }}" value="1"
                                       class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                                Remover esta imagem
                            </label>
                        @endif

                        <input type="file" name="{{ $qual }}_image" id="{{ $qual }}_image" accept=".png,.jpg,.jpeg"
                               class="block w-full text-sm text-ink">
                        <p class="mt-1 text-xs text-ink-2">
                            PNG ou JPG, até 2 MB. {{ $ajuda }}
                            @if($imagem) Enviar outra substitui a atual. @endif
                        </p>
                        @error($qual . '_image')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror

                        <div class="mt-3" style="max-width: 16rem;">
                            <label for="{{ $qual }}_height_mm" class="block text-xs font-bold text-ink-2 mb-1">
                                Altura do {{ mb_strtolower($titulo) }} (mm)
                            </label>
                            <input type="number" name="{{ $qual }}_height_mm" id="{{ $qual }}_height_mm"
                                   min="{{ $qual === 'header' ? 8 : 6 }}" max="{{ $qual === 'header' ? 60 : 50 }}"
                                   value="{{ old($qual . '_height_mm', $layout?->{$qual . '_height_mm'} ?? ($qual === 'header' ? 22 : 16)) }}"
                                   class="{{ $campo }}">
                            @error($qual . '_height_mm')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endforeach

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2 border-t border-line">
                    <div>
                        <label for="align" class="block text-sm font-bold text-ink mb-1">Posição das imagens</label>
                        <select name="align" id="align" class="{{ $campo }}">
                            @foreach(\App\Models\SignatureLayout::ALIGNMENTS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('align', $layout?->align ?? 'left') === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-2">
                            A imagem é reduzida para caber na altura informada e na largura do texto, sem deformar.
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Faixa de borda a borda</label>
                        <label class="flex items-center gap-2 mt-3 text-sm text-ink">
                            <input type="hidden" name="full_width" value="0">
                            <input type="checkbox" name="full_width" value="1"
                                   @checked(old('full_width', $layout?->full_width ?? false))
                                   class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                            Ocupar a largura inteira do papel
                        </label>
                        <p class="mt-1 text-xs text-ink-2">
                            Para arte que vai de uma borda à outra da folha. A largura passa a ser a do papel, e a
                            altura sai da proporção da imagem — a altura e a posição acima deixam de valer.
                        </p>
                    </div>
                </div>

                <div>
                    <label for="footer_text" class="block text-sm font-bold text-ink mb-1">Texto do rodapé</label>
                    <input type="text" name="footer_text" id="footer_text" maxlength="500"
                           value="{{ old('footer_text', $layout?->footer_text) }}"
                           placeholder="Razão social · CNPJ · endereço · telefone"
                           class="{{ $campo }}">
                    <p class="mt-1 text-xs text-ink-2">
                        Uma linha, impressa em todas as páginas acima do código de validação.
                    </p>
                    @error('footer_text')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <a href="{{ route('signature-layout.preview') }}" target="_blank"
                   class="px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition">
                    Ver exemplo em PDF (do que está salvo)
                </a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition">
                    Salvar papel timbrado
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>

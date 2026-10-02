@php
    /**
     * Envio de um documento PRONTO, em PDF.
     *
     * Reaproveita o formulário do documento de modelo (título, local,
     * signatários) passando um modelo vazio, e acrescenta o que aqui não vem
     * de modelo nenhum: o arquivo e as regras da assinatura.
     */
    $campo = 'w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[900px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Enviar documento pronto" :back="route('signature-documents.create')">
            Um PDF já preenchido, aproveitado na íntegra. O sistema só acrescenta a assinatura e o visto.
        </x-page-title>

        @include('partials.alerts')

        <form action="{{ route('signature-documents.upload.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="bg-surface rounded-card shadow-card p-6 space-y-5 mb-6"
                 data-step="Arquivo e regras" data-step-keys="file,identity_check,requires_photo,requires_initials">
                <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Arquivo</h3>

                <div>
                    <label for="file" class="block text-sm font-bold text-ink mb-1">Documento em PDF</label>
                    <input type="file" name="file" id="file" accept=".pdf,application/pdf" required
                           class="block w-full text-sm text-ink">
                    <p class="mt-1 text-xs text-ink-2">
                        Até 20 MB. Texto, imagens e diagramação entram como estão — nada é refeito. Deixe em branco só
                        o lugar da assinatura. Do Word, use <strong>Salvar como → PDF</strong>.
                    </p>
                    @error('file')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2 border-t border-line">
                    <div>
                        <label for="identity_check" class="block text-sm font-bold text-ink mb-1">
                            Conferência de identidade
                        </label>
                        <select name="identity_check" id="identity_check" class="{{ $campo }}">
                            @foreach(\App\Models\SignatureTemplate::IDENTITY_CHECKS as $valor => $rotulo)
                                <option value="{{ $valor }}" @selected(old('identity_check', 'partial') === $valor)>{{ $rotulo }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-ink-2">O que a pessoa digita no tablet antes de assinar.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-ink mb-1">Evidências</label>
                        <label class="flex items-center gap-2 mt-3 text-sm text-ink">
                            <input type="hidden" name="requires_photo" value="0">
                            <input type="checkbox" name="requires_photo" value="1" @checked(old('requires_photo', true))
                                   class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                            Capturar foto na confirmação
                        </label>
                        <label class="flex items-center gap-2 mt-3 text-sm text-ink">
                            <input type="hidden" name="requires_initials" value="0">
                            <input type="checkbox" name="requires_initials" value="1" @checked(old('requires_initials', false))
                                   class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                            Visto em todas as páginas
                        </label>
                        <p class="mt-1 text-xs text-ink-2">
                            O visto é carimbado no canto de baixo, à direita, de cada página do PDF.
                        </p>
                    </div>
                </div>
            </div>

            @include('signature.documents.partials.form', ['document' => null, 'template' => $template])

            <div class="mt-6 flex justify-end gap-3" data-step-actions>
                <a href="{{ route('signature-documents.index') }}" class="px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition">Enviar e criar rascunho</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>

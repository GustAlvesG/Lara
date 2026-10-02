<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[900px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Novo documento" :back="route('signature-documents.index')">
            Escolha o modelo, preencha os dados e libere para assinatura no tablet.
        </x-page-title>

        @include('partials.alerts')

        @if(!$template)
            {{-- Passo 1: escolher o modelo. Só depois o formulário sabe quais dados pedir. --}}
            <div class="bg-surface rounded-card shadow-card p-6">
                <h3 class="text-lg font-bold text-ink mb-1">Escolha o modelo</h3>
                <p class="text-sm text-ink-2 mb-6">
                    O modelo define o texto do documento, quais dados são preenchidos e como a identidade é conferida no tablet.
                </p>

                {{-- O caminho sem modelo: um PDF já pronto, aproveitado como está. --}}
                <a href="{{ route('signature-documents.upload') }}"
                   class="block rounded-xl border border-line p-5 mb-4 hover:border-grena hover:shadow-card transition">
                    <div class="font-bold text-ink">Enviar documento pronto (PDF)</div>
                    <div class="text-xs text-ink-2 mt-1">
                        Para um documento já preenchido, com imagens e diagramação próprias. Ele entra na íntegra:
                        o sistema só acrescenta a assinatura e o visto.
                    </div>
                </a>

                @if($templates->isEmpty())
                    <p class="text-sm text-ink-2">
                        Nenhum modelo ativo. Cadastre um em <a href="{{ route('signature-templates.index') }}" class="text-grena-ink font-bold hover:underline">Modelos</a>.
                    </p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($templates as $modelo)
                            <a href="{{ route('signature-documents.create', ['template' => $modelo->id]) }}"
                               class="block rounded-xl border border-line p-5 hover:border-grena hover:shadow-card transition">
                                <div class="font-bold text-ink">{{ $modelo->name }}</div>
                                <div class="text-xs text-ink-2 mt-1">{{ $modelo->description }}</div>
                                <div class="text-[11px] text-ink-3 mt-2">
                                    versão {{ $modelo->version }}
                                    · {{ \App\Models\SignatureTemplate::IDENTITY_CHECKS[$modelo->identity_check] ?? '' }}
                                    @if($modelo->requires_photo) · com foto @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <p class="text-xs text-ink-2">Modelo</p>
                    <p class="font-bold text-ink">{{ $template->name }} <span class="text-ink-3">v{{ $template->version }}</span></p>
                </div>
                <a href="{{ route('signature-documents.create') }}" class="text-xs text-ink-2 hover:underline">Trocar modelo</a>
            </div>

            <form action="{{ route('signature-documents.store') }}" method="POST">
                @csrf
                <input type="hidden" name="signature_template_id" value="{{ $template->id }}">

                @include('signature.documents.partials.form', ['document' => null, 'template' => $template])

                <div class="mt-6 flex justify-end gap-3" data-step-actions>
                    <a href="{{ route('signature-documents.index') }}" class="px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                    <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition">Criar rascunho</button>
                </div>
            </form>
        @endif
    </div>
</div>
</x-app-layout>

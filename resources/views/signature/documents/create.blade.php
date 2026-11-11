<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[900px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Novo documento" :back="\App\Support\DocumentRoutes::route('index')">
            Escolha o modelo, preencha os dados e libere para assinatura no tablet.
        </x-page-title>

        @include('partials.alerts')

        @if(!$template)
            {{-- Passo 1: escolher o modelo. Só depois o formulário sabe quais dados pedir. --}}
            {{-- Escolha do modelo (UX, 11/11/2026): cartões com busca na página. --}}
            <div class="flex flex-col gap-5 rounded-card bg-surface p-6 shadow-card">
                <div>
                    <p class="lara-step-tag">Passo 1 · Modelo</p>
                    <h3 class="font-display text-xl font-semibold tracking-tight text-ink">Escolha o modelo</h3>
                    <p class="mt-1 text-sm text-ink-2">
                        O modelo define o texto do documento, quais dados são preenchidos e como a identidade é conferida no tablet.
                    </p>
                </div>

                {{-- O caminho sem modelo: um PDF já pronto, aproveitado como está. --}}
                <a href="{{ route('signature-documents.upload') }}"
                   class="group flex items-start gap-4 rounded-2xl border-2 border-dashed border-line-strong p-5 no-underline transition hover:border-[rgb(var(--pen-ink))] hover:bg-subtle dark:hover:border-[rgb(var(--pen-glow))]">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-subtle text-ink-2 group-hover:bg-[rgb(var(--pen-ink))] group-hover:text-white">
                        <x-icon name="download" class="h-5 w-5 rotate-180" />
                    </span>
                    <span>
                        <span class="block font-bold text-ink">Enviar documento pronto (PDF)</span>
                        <span class="mt-1 block text-sm text-ink-2">
                            Para um documento já preenchido, com imagens e diagramação próprias. Ele entra na íntegra:
                            o sistema só acrescenta a assinatura e o visto.
                        </span>
                    </span>
                </a>

                @if($templates->isEmpty())
                    <p class="text-sm text-ink-2">
                        Nenhum modelo ativo. Cadastre um em <a href="{{ route('signature-templates.index') }}" class="text-grena-ink font-bold hover:underline">Modelos</a>.
                    </p>
                @else
                    @if($templates->count() > 6)
                        <x-search-bar mode="client" target="#modelos-assinatura" placeholder="Buscar modelo pelo nome ou descrição" />
                    @endif
                    <div id="modelos-assinatura" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        @foreach($templates as $modelo)
                            <a href="{{ route('signature-documents.create', ['template' => $modelo->id]) }}" data-search="{{ $modelo->name }} {{ $modelo->description }}"
                               class="group flex items-start gap-4 rounded-2xl border border-line p-5 no-underline transition hover:-translate-y-0.5 hover:border-line-strong hover:shadow-card motion-reduce:hover:translate-y-0">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[rgb(var(--pen-ink))] text-white">
                                    <x-icon name="doc" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block font-bold text-ink group-hover:text-grena-ink">{{ $modelo->name }}</span>
                                    @if($modelo->description)
                                        <span class="mt-1 block text-sm text-ink-2">{{ $modelo->description }}</span>
                                    @endif
                                    <span class="mt-2 flex flex-wrap gap-1.5">
                                        <x-pill kind="off" :icon="false">v{{ $modelo->version }}</x-pill>
                                        @if(\App\Models\SignatureTemplate::IDENTITY_CHECKS[$modelo->identity_check] ?? null)
                                            <x-pill kind="info" :icon="false">{{ \App\Models\SignatureTemplate::IDENTITY_CHECKS[$modelo->identity_check] }}</x-pill>
                                        @endif
                                        @if($modelo->requires_photo)<x-pill kind="warn" :icon="false">com foto</x-pill>@endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @else
            {{-- Modelo escolhido: resumo fixo acima dos passos (UX, 11/11/2026). --}}
            <div class="flex items-start justify-between gap-4 rounded-card bg-surface p-5 shadow-card">
                <div class="flex min-w-0 items-start gap-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[rgb(var(--pen-ink))] text-white">
                    <x-icon name="doc" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-3">Modelo escolhido</p>
                    <p class="font-display text-lg font-semibold tracking-tight text-ink">{{ $template->name }} <span class="font-mono text-sm font-normal text-ink-3">v{{ $template->version }}</span></p>
                    @if($template->composedParts)
                        {{-- Modelo vinculado: o texto dos exigidos entra no mesmo documento, em página nova. --}}
                        <p class="text-xs text-ink-2 mt-1" data-linked-templates>
                            Inclui também, no mesmo documento:
                            {{ collect($template->composedParts)->map(fn($p) => $p['name'] . ' (v' . $p['version'] . ')')->join(', ') }}.
                            Os campos em comum são preenchidos uma vez só.
                        </p>
                    @endif
                    @foreach($template->compositionProblems as $problema)
                        <p class="text-xs text-danger mt-1">{{ $problema }}</p>
                    @endforeach
                    @error('signature_template_id')<p class="text-xs text-danger mt-1">{{ $message }}</p>@enderror
                </div>
                </div>
                <x-secondary-button-a href="{{ route('signature-documents.create') }}" class="shrink-0">Trocar modelo</x-secondary-button-a>
            </div>

            <form action="{{ route('signature-documents.store') }}" method="POST" class="lara-form">
                @csrf
                <input type="hidden" name="signature_template_id" value="{{ $template->id }}">

                @include('signature.documents.partials.form', ['document' => null, 'template' => $template])

                <div class="mt-6 flex justify-end gap-3" data-step-actions>
                    <a href="{{ \App\Support\DocumentRoutes::route('index') }}" class="px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                    <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition">Criar rascunho</button>
                </div>
            </form>
        @endif
    </div>
</div>
</x-app-layout>

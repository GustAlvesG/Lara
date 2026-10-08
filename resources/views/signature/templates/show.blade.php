<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[1040px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title :title="$template->name . ' — versão ' . $template->version" :back="route('signature-templates.index')"></x-page-title>

        @include('partials.alerts')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <span></span>

            <div class="flex gap-3">
                @if($template->active)
                    <a href="{{ route('signature-documents.create', ['template' => $template->id]) }}"
                       class="px-5 py-2.5 rounded-full font-bold text-sm bg-subtle text-ink hover:bg-line transition">
                        Emitir documento
                    </a>
                @endif
                <a href="{{ route('signature-templates.edit', $template) }}"
                   class="px-5 py-2.5 bg-grena text-white rounded-full font-bold text-sm hover:bg-grena-hover transition">
                    Revisar
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-surface rounded-card shadow-card p-6">
                <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-4">Texto</h3>

                {{-- Prévia do corpo saneado. Sai sem escapar porque é HTML da allow-list, saneado na gravação. --}}
                <div class="prose prose-sm max-w-none dark:prose-invert text-ink leading-relaxed">
                    {!! $template->body_html !!}
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-surface rounded-card shadow-card p-6">
                    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-4">Regras</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs text-ink-2">Conferência de identidade</dt>
                            <dd class="font-semibold text-ink">
                                {{ \App\Models\SignatureTemplate::IDENTITY_CHECKS[$template->identity_check] ?? $template->identity_check }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-2">Foto do signatário</dt>
                            <dd class="font-semibold text-ink">
                                {{ $template->requires_photo ? 'Exigida' : 'Não capturada' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-2">Visto em todas as páginas</dt>
                            <dd class="font-semibold text-ink">
                                {{ $template->requires_initials ? 'Exigido' : 'Não' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-2">Quem assina</dt>
                            <dd class="font-semibold text-ink">
                                {{ collect($template->declaredParties())->pluck('label')->join(', ') ?: 'Sem partes — todos no mesmo lugar' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-2">Prazo de guarda</dt>
                            <dd class="font-semibold text-ink">
                                {{ $template->retention_months ? $template->retention_months . ' meses' : 'Padrão (' . config('signature.retention_months') . ' meses)' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-ink-2">Situação</dt>
                            <dd class="font-semibold text-ink">{{ $template->active ? 'Ativo' : 'Inativo' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-surface rounded-card shadow-card p-6">
                    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-4">Campos</h3>
                    @if(empty($template->declaredVariables()))
                        <p class="text-sm text-ink-2">Nenhuma — o texto é fixo.</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach($template->declaredVariables() as $variavel)
                                <li class="flex items-start justify-between gap-2">
                                    <span class="font-mono text-xs text-ink-2">[[{{ $variavel['key'] }}]]</span>
                                    <span class="text-right text-ink">
                                        {{ $variavel['label'] }}
                                        <span class="block text-[10px] text-ink-2">
                                            {{ \App\Services\Signature\SignatureFieldTypes::LABELS[$variavel['type']] }}
                                        </span>
                                        @if(\App\Services\Signature\SignatureFieldTypes::isAutomatic($variavel['type']))
                                            <span class="block text-[10px] text-ink-2">preenchido pelo sistema</span>
                                        @elseif($variavel['question'] !== $variavel['label'])
                                            <span class="block text-[10px] text-ink-2">
                                                no tablet: {{ $variavel['question'] }}
                                            </span>
                                        @endif
                                        @if($variavel['options'])
                                            <span class="block text-[10px] text-ink-2">{{ implode(' · ', $variavel['options']) }}</span>
                                        @endif
                                        @if($variavel['required'])
                                            <span class="block text-[10px] font-bold text-grena-ink">obrigatória</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="bg-surface rounded-card shadow-card p-6" data-template-attachments>
                    <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider mb-4">Anexos pedidos</h3>
                    @if(empty($template->declaredAttachments()))
                        <p class="text-sm text-ink-2">Nenhum.</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach($template->declaredAttachments() as $anexo)
                                <li class="flex items-start justify-between gap-2">
                                    <span class="text-ink">{{ $anexo['label'] }}</span>
                                    <span class="text-[10px] {{ $anexo['required'] ? 'font-bold text-grena-ink' : 'text-ink-2' }}">
                                        {{ $anexo['required'] ? 'obrigatório' : 'opcional' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-surface rounded-card shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-line">
                <h3 class="text-sm font-bold text-ink-3 uppercase tracking-wider">Histórico de versões</h3>
                <p class="text-xs text-ink-2 mt-1">
                    Cada versão continua existindo porque é para ela que apontam os documentos emitidos sob o texto dela.
                </p>
            </div>
            <table class="w-full text-sm text-left">
                <thead class="text-xs font-bold text-ink-3 uppercase tracking-wider bg-subtle">
                    <tr>
                        <th class="px-6 py-3">Versão</th>
                        <th class="px-6 py-3">Criada em</th>
                        <th class="px-6 py-3">Documentos emitidos</th>
                        <th class="px-6 py-3">Situação</th>
                        <th class="px-6 py-3 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach($versoes as $versao)
                        <tr class="{{ $versao->id === $template->id ? 'bg-subtle' : '' }}">
                            <td class="px-6 py-4 font-semibold text-ink">v{{ $versao->version }}</td>
                            <td class="px-6 py-4 text-ink">{{ $versao->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-6 py-4 text-ink">{{ $usos[$versao->id] ?? 0 }}</td>
                            <td class="px-6 py-4 text-ink">{{ $versao->active ? 'Ativa' : 'Substituída' }}</td>
                            <td class="px-6 py-4 text-right">
                                @if($versao->id !== $template->id)
                                    <a href="{{ route('signature-templates.show', $versao) }}" class="text-grena-ink hover:underline text-xs font-medium">Ver texto</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-app-layout>

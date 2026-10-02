{{-- Modelos de documento da assinatura. A lista vem inteira: a busca filtra na página. --}}
@php
    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Modelos">
            O texto dos termos, fichas e contratos assinados no tablet. Revisar um modelo cria a
            versão seguinte — os documentos já emitidos continuam com o texto que imprimiram.
            <x-slot:actions>
                <a href="{{ route('signature-layout.edit') }}"
                   class="px-5 py-2.5 rounded-full font-bold text-sm bg-subtle text-ink hover:bg-line transition">
                    Cabeçalho e rodapé
                </a>
                <x-primary-button-a href="{{ route('signature-templates.create') }}"><x-icon name="plus" /> Novo modelo</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if($templates->isEmpty())
            <x-empty-state icon="doc">
                Nenhum modelo cadastrado.
                <a href="{{ route('signature-templates.create') }}" class="font-bold text-grena-ink hover:underline">Criar o primeiro</a>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#modelos-assinatura" placeholder="Buscar modelo" />

            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Modelo</th>
                                <th class="{{ $th }}">Versão</th>
                                <th class="{{ $th }}">Identidade</th>
                                <th class="{{ $th }}">Foto</th>
                                <th class="{{ $th }} text-right">Documentos</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody id="modelos-assinatura" class="divide-y divide-line">
                            @foreach($templates as $template)
                            <tr data-search="{{ $template->active ? 'ativo' : 'inativo' }}" class="transition hover:bg-subtle">
                                <td class="px-5 py-3.5">
                                    <a href="{{ route('signature-templates.show', $template) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $template->name }}</a>
                                    @if($template->description)
                                        <div class="text-xs text-ink-2">{{ $template->description }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-mono text-ink-2">v{{ $template->version }}</td>
                                <td class="px-5 py-3.5 text-xs text-ink-2">
                                    {{ \App\Models\SignatureTemplate::IDENTITY_CHECKS[$template->identity_check] ?? $template->identity_check }}
                                </td>
                                <td class="px-5 py-3.5 text-xs text-ink-2">
                                    {{ $template->requires_photo ? 'Exigida' : 'Não' }}
                                </td>
                                <td class="px-5 py-3.5 text-right font-mono text-ink-2">
                                    {{ $usos[$template->id] ?? 0 }}
                                </td>
                                <td class="px-5 py-3.5">
                                    <x-pill :kind="$template->active ? 'ok' : 'off'">{{ $template->active ? 'Ativo' : 'Inativo' }}</x-pill>
                                </td>
                                <td class="whitespace-nowrap px-5 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <x-secondary-button-a size="sm" href="{{ route('signature-templates.show', $template) }}"><x-icon name="eye" /> Ver</x-secondary-button-a>
                                        <x-secondary-button-a size="sm" href="{{ route('signature-templates.edit', $template) }}"><x-icon name="pencil" /> Revisar</x-secondary-button-a>
                                        <form method="POST" action="{{ route('signature-templates.destroy', $template) }}"
                                              onsubmit="return confirm('Excluir (ou desativar, se já usado) o modelo \'{{ $template->name }}\'?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" aria-label="Excluir {{ $template->name }}"
                                                    class="grid h-8 w-8 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-page>
</x-app-layout>

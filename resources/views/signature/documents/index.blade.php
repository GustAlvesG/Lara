{{--
    Documentos da assinatura eletrônica. Lista paginada: a busca (título,
    signatário, CPF ou código de validação) e a situação vão ao servidor.
--}}
@php
    use App\Models\SignatureDocument as Doc;

    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $statusKind = fn (string $status) => match (true) {
        $status === Doc::STATUS_AWAITING_SIGNATURE => 'warn',
        in_array($status, [Doc::STATUS_SIGNED, Doc::STATUS_FINALIZED], true) => 'ok',
        in_array($status, [Doc::STATUS_REFUSED, Doc::STATUS_CANCELED, Doc::STATUS_EXPIRED], true) => 'danger',
        default => 'off',
    };
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Documentos">
            Termos, fichas e contratos assinados no tablet do balcão.
            <x-slot:actions>
                @can('create', Doc::class)
                    <x-primary-button-a href="{{ route('signature-documents.create') }}"><x-icon name="plus" /> Novo documento</x-primary-button-a>
                @endcan
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar :filters="['status']" placeholder="Título, nome do signatário, CPF ou código de validação">
            <x-slot:controls>
                <label for="status" class="sr-only">Situação</label>
                <select name="status" id="status" class="{{ $field }} w-full sm:w-56">
                    <option value="">Todas as situações</option>
                    @foreach(Doc::STATUS_LABELS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($status === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if($documents->isEmpty())
            <x-empty-state icon="doc">
                @if(filled($busca) || filled($status))
                    Nenhum documento com esses filtros.
                    <a href="{{ route('signature-documents.index') }}" class="font-bold text-grena-ink hover:underline">Limpar a busca</a>.
                @else
                    Nenhum documento por aqui ainda.
                    @can('create', Doc::class)
                        <a href="{{ route('signature-documents.create') }}" class="font-bold text-grena-ink hover:underline">Criar o primeiro</a>.
                    @endcan
                @endif
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Documento</th>
                                <th class="{{ $th }}">Signatários</th>
                                <th class="{{ $th }}">Situação</th>
                                <th class="{{ $th }}">Criado em</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($documents as $document)
                                <tr class="transition hover:bg-subtle">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('signature-documents.show', $document) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $document->title }}</a>
                                        <div class="text-xs text-ink-2">
                                            {{ $document->template?->name }} v{{ $document->template_version }}
                                            @if($document->validation_code)
                                                · <span class="font-mono">{{ $document->validation_code }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-ink">
                                        @foreach($document->signers as $signer)
                                            <div>{{ $signer->name }} <span class="text-ink-3">— {{ $signer->statusLabel() }}</span></div>
                                        @endforeach
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <x-pill :kind="$statusKind($document->status)">{{ $document->statusLabel() }}</x-pill>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-ink-2">
                                        {{ $document->created_at?->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <x-secondary-button-a size="sm" href="{{ route('signature-documents.show', $document) }}">Abrir</x-secondary-button-a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $documents->links() }}
        @endif
    </x-page>
</x-app-layout>

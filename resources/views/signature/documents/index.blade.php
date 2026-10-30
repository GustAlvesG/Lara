{{--
    Documentos da assinatura eletrônica. Lista paginada: a busca
    (SignatureDocument::scopeSearch — cada palavra em título, modelo, código,
    quem gerou, nome/e-mail/CPF do signatário, número e partes do contrato) e os filtros (situação, modelo ou contratos,
    período, gerados por mim, arquivados) vão ao servidor. Arquivado
    (`hidden_at`) só aparece com o filtro "Arquivados".
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

        <x-search-bar :filters="['status', 'modelo', 'de', 'ate', 'meus', 'arquivados']" placeholder="Título, modelo, signatário, e-mail, CPF, código, nº do contrato, contratada ou CNPJ">
            <x-slot:controls>
                <label for="status" class="sr-only">Situação</label>
                <select name="status" id="status" class="{{ $field }} w-full sm:w-56">
                    <option value="">Todas as situações</option>
                    @foreach(Doc::STATUS_LABELS as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($status === $valor)>{{ $rotulo }}</option>
                    @endforeach
                    <option value="{{ Doc::FILTER_AWAITING_REVIEW }}" @selected($status === Doc::FILTER_AWAITING_REVIEW)>{{ Doc::LABEL_AWAITING_REVIEW }} (aguardando revisão)</option>
                    {{-- Aguardando a assinatura de um diretor (Assinatura Diretoria). --}}
                    <option value="diretoria" @selected($status === 'diretoria')>Aguardando diretoria</option>
                </select>

                <label for="modelo" class="sr-only">Modelo</label>
                <select name="modelo" id="modelo" class="{{ $field }} w-full sm:w-64">
                    <option value="">Todos os modelos</option>
                    <option value="contratos" @selected($modelo === 'contratos')>Contratos</option>
                    @foreach($modelos as $opcao)
                        <option value="{{ $opcao->root_id }}" @selected($modelo === (string) $opcao->root_id)>
                            {{ $opcao->name }}{{ $opcao->active ? '' : ' (inativo)' }}
                        </option>
                    @endforeach
                    <option value="avulso" @selected($modelo === 'avulso')>Documento enviado pronto (PDF)</option>
                </select>

                <div class="flex w-full items-center gap-2 sm:w-auto">
                    <label for="de" class="text-xs font-bold text-ink-3">De</label>
                    <input type="date" name="de" id="de" value="{{ $de }}" class="{{ $field }} flex-1 sm:w-40">
                    <label for="ate" class="text-xs font-bold text-ink-3">até</label>
                    <input type="date" name="ate" id="ate" value="{{ $ate }}" class="{{ $field }} flex-1 sm:w-40">
                </div>

                <label class="inline-flex items-center gap-2 whitespace-nowrap px-1 text-sm text-ink">
                    <input type="checkbox" name="meus" value="1" @checked($meus)
                           class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                    Gerados por mim
                </label>

                {{-- Arquivados saem da lista padrão (só exibição — ver DocumentController::hide). --}}
                <label class="inline-flex items-center gap-2 whitespace-nowrap px-1 text-sm text-ink">
                    <input type="checkbox" name="arquivados" value="1" @checked($arquivados)
                           class="rounded border-line-strong text-grena-ink focus:ring-grena-tint">
                    Arquivados
                </label>
            </x-slot:controls>
        </x-search-bar>

        @if($documents->isNotEmpty() && (filled($busca) || filled($status) || filled($modelo) || $de || $ate || $meus || $arquivados))
            <p class="px-1 text-[13px] text-ink-3" aria-live="polite">
                {{ $documents->total() }} {{ $documents->total() === 1 ? 'documento encontrado' : 'documentos encontrados' }}
            </p>
        @endif

        @if($documents->isEmpty())
            <x-empty-state icon="doc">
                @if(filled($busca) || filled($status) || filled($modelo) || $de || $ate || $meus || $arquivados)
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
                                            @if($document->contract)
                                                <b>Contrato</b> ·
                                            @endif
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
                                        @php $rotulo = $document->statusLabel(); @endphp
                                        <x-pill :kind="$rotulo === Doc::LABEL_AWAITING_ATTACHMENTS ? 'warn' : $statusKind($document->status)">{{ $rotulo }}</x-pill>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-xs text-ink-2">
                                        <span class="font-mono">{{ $document->created_at?->format('d/m/Y H:i') }}</span>
                                        @if($document->created_by_name)
                                            <div>por {{ $document->created_by_name }}</div>
                                        @endif
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

            <p class="mt-3 text-xs text-ink-2">* {{ Doc::LABEL_AWAITING_REVIEW }}: todos assinaram e o PDF está pronto. Vira Finalizado depois da revisão interna em ordem.</p>

            {{ $documents->links() }}
        @endif
    </x-page>
</x-app-layout>

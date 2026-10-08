{{--
    Fila da revisão interna (Assinaturas → Revisão): documentos concluídos que
    ainda não foram revisados em ordem. Ver SignatureReviewService e ReviewController.
    Os que esta pessoa ainda não pode revisar (no prazo, ou que ela acompanhou)
    aparecem com o motivo, para ninguém achar que sumiram.
--}}
@php
    use App\Models\SignatureReview;

    $th = 'px-5 py-3 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Revisão">
            Documentos assinados à espera da revisão interna: outra pessoa confere se os processos do atendimento
            foram feitos, a partir do dia seguinte à conclusão.
            @if($coordenacao)
                Como coordenação, você revisa antes do prazo e também os documentos que acompanhou.
            @endif
        </x-page-title>

        @include('partials.alerts')

        <x-search-bar :filters="['situacao']" placeholder="Título, nome do signatário, CPF ou código de validação">
            <x-slot:controls>
                <label for="situacao" class="sr-only">Situação</label>
                <select name="situacao" id="situacao" class="{{ $field }} w-full sm:w-56">
                    <option value="pendentes" @selected($situacao === 'pendentes')>A revisar</option>
                    <option value="revisados" @selected($situacao === 'revisados')>Revisados em ordem</option>
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if($documents->isEmpty())
            <x-empty-state icon="doc">
                {{ $situacao === 'revisados' ? 'Nenhum documento revisado com esses filtros.' : 'Nada para revisar por aqui.' }}
            </x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line">
                                <th class="{{ $th }}">Documento</th>
                                <th class="{{ $th }}">Concluído em</th>
                                <th class="{{ $th }}">Gerado por</th>
                                <th class="{{ $th }}">Revisão</th>
                                <th class="{{ $th }}"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach($documents as $document)
                                @php $motivo = $motivos[$document->id] ?? null; @endphp
                                <tr class="transition hover:bg-subtle" data-review-row="{{ $document->id }}">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('signature-documents.show', [$document, 'aba' => 'revisao']) }}" class="font-semibold text-ink hover:text-grena-ink">{{ $document->title }}</a>
                                        <div class="text-xs text-ink-2">
                                            {{ $document->template?->name }}
                                            @if($document->validation_code)
                                                · <span class="font-mono">{{ $document->validation_code }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 font-mono text-xs text-ink-2">
                                        {{ $document->finalized_at?->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-5 py-3.5 text-xs text-ink">{{ $document->created_by_name ?? '—' }}</td>
                                    <td class="px-5 py-3.5">
                                        <x-pill :kind="match ($document->review_status) {
                                            SignatureReview::RESULT_OK => 'ok',
                                            SignatureReview::RESULT_ISSUES => 'danger',
                                            default => 'warn',
                                        }">{{ $document->reviewStatusLabel() }}</x-pill>
                                        @if($situacao === 'pendentes' && $motivo)
                                            <div class="mt-1 text-xs text-ink-2">{{ $motivo }}</div>
                                        @elseif($situacao === 'revisados' && $document->reviewed_at)
                                            <div class="mt-1 font-mono text-xs text-ink-2">{{ $document->reviewed_at->format('d/m/Y H:i') }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right">
                                        <x-secondary-button-a size="sm" href="{{ route('signature-documents.show', [$document, 'aba' => 'revisao']) }}">
                                            {{ $situacao === 'pendentes' && $motivo === null ? 'Revisar' : 'Abrir' }}
                                        </x-secondary-button-a>
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

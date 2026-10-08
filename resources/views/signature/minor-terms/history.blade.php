{{--
    Assinaturas → Termo de Menores → Histórico. Para quem põe a pulseira na
    entrada, quando a tela verde do tablet já saiu: cada cartão repete o que a
    tela verde mostrou (menor, idade, responsável, foto, evento, hora).
--}}
@php
    use App\Models\SignatureDocument;

    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Termo de Menores">
            Menores autorizados pelos responsáveis no tablet de autoatendimento. Confira o nome, a idade e a foto de
            quem assinou antes de colocar a pulseira.
        </x-page-title>

        @include('signature.minor-terms.partials.tabs', ['active' => 'history'])
        @include('partials.alerts')

        <x-search-bar :filters="['termo', 'situacao']" placeholder="Nome do menor, do responsável ou número do título">
            <x-slot:controls>
                <label for="termo" class="sr-only">Evento</label>
                <select name="termo" id="termo" class="{{ $field }} w-full sm:w-64">
                    @forelse($terms as $t)
                        <option value="{{ $t->id }}" @selected($termId === $t->id)>{{ $t->name }} ({{ $t->periodLabel() }})</option>
                    @empty
                        <option value="">Nenhum termo cadastrado</option>
                    @endforelse
                </select>

                <label for="situacao" class="sr-only">Situação</label>
                <select name="situacao" id="situacao" class="{{ $field }} w-full sm:w-56">
                    <option value="autorizados" @selected($situacao === 'autorizados')>Só os autorizados</option>
                    <option value="todos" @selected($situacao === 'todos')>Todos (inclui não concluídos)</option>
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if($authorizations->isEmpty())
            <x-empty-state icon="doc">Nenhuma autorização com esses filtros.</x-empty-state>
        @else
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach($authorizations as $aut)
                    @php
                        $documento = $aut->document;
                        $signatario = $documento?->signers->first();
                        $autorizado = $aut->isAuthorized();
                        $temFoto = (bool) $signatario?->evidence?->photo_path;
                    @endphp
                    <article class="flex gap-4 rounded-card border-2 bg-surface p-4 shadow-card {{ $autorizado ? 'border-ok' : 'border-line' }}"
                             data-minor-authorization="{{ $aut->id }}">
                        <div class="h-28 w-28 shrink-0 overflow-hidden rounded-xl bg-subtle">
                            @if($temFoto)
                                <img src="{{ route('minor-terms.history.photo', $aut) }}" alt="Foto de {{ $aut->responsible_name }}"
                                     class="h-full w-full object-cover" loading="lazy">
                            @else
                                <div class="grid h-full w-full place-items-center text-xs text-ink-3">Sem foto</div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="mb-1">
                                <x-pill :kind="$autorizado ? 'ok' : ($documento?->status === SignatureDocument::STATUS_AWAITING_SIGNATURE ? 'warn' : 'danger')">
                                    {{ $autorizado ? 'Autorizado' : ($documento?->statusLabel() ?? 'Sem documento') }}
                                </x-pill>
                            </div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Menor</div>
                            <div class="truncate text-base font-bold text-ink">{{ $aut->minor_name }}</div>
                            <div class="text-sm text-ink-2">{{ $aut->minorAgeOn() }} anos</div>

                            <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Responsável</div>
                            <div class="truncate text-sm font-semibold text-ink">{{ $aut->responsible_name }}</div>

                            <div class="mt-2 text-xs text-ink-2">
                                {{ $aut->term?->name }} · Título <span class="font-mono">{{ $aut->title_code }}</span>
                                · <span class="font-mono">{{ ($aut->signedAt() ?? $aut->created_at)?->format('d/m/Y H:i') }}</span>
                            </div>

                            @if($canSeeDocument && $documento)
                                <a href="{{ route('signature-documents.show', $documento) }}" class="mt-2 inline-block text-xs font-semibold text-grena-ink hover:underline">
                                    Abrir documento
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            {{ $authorizations->links() }}
        @endif
    </x-page>
</x-app-layout>

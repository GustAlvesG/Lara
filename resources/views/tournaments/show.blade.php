{{-- Um torneio: dados, situação e as categorias ligadas, com as contagens de
     inscrição. A lista de inscritos é a tela de Inscrições. --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\Tournament\Tournament;
    use App\Models\Tournament\TournamentCategory as Category;
    use App\Models\Tournament\TournamentRegistration as Registration;

    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $count = fn (int $categoryId, string $status) => (int) optional(($counts[$categoryId] ?? collect())->firstWhere('status', $status))->total;
    $actions = [
        Tournament::STATUS_PUBLISHED => ['Publicar no site', 'primary'],
        Tournament::STATUS_DRAFT => ['Voltar para rascunho', 'secondary'],
        Tournament::STATUS_IN_PROGRESS => ['Iniciar torneio', 'secondary'],
        Tournament::STATUS_FINISHED => ['Encerrar torneio', 'secondary'],
        Tournament::STATUS_CANCELLED => ['Cancelar torneio', 'danger'],
    ];
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$tournament->title" :back="route('tournaments.index')">
            {{ $tournament->placeGroup?->name }} · {{ $tournament->starts_on->format('d/m/Y') }}@unless ($tournament->ends_on->isSameDay($tournament->starts_on)) a {{ $tournament->ends_on->format('d/m/Y') }}@endunless
            <x-slot:actions>
                @can(P::TORNEIOS_INSCRICOES)
                    <x-secondary-button-a href="{{ route('tournaments.registrations.index', $tournament) }}"><x-icon name="users" /> Inscrições</x-secondary-button-a>
                @endcan
                @can(P::TORNEIOS_FINANCEIRO)
                    <x-secondary-button-a href="{{ route('tournaments.finance.show', $tournament) }}"><x-icon name="money" /> Financeiro</x-secondary-button-a>
                @endcan
                <x-secondary-button-a href="{{ route('tournaments.edit', $tournament) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <section class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Situação</p>
                <p class="mt-1"><x-pill :kind="$tournament->status === Tournament::STATUS_PUBLISHED ? 'ok' : ($tournament->status === Tournament::STATUS_CANCELLED ? 'danger' : 'off')">{{ Tournament::STATUS_LABELS[$tournament->status] }}</x-pill></p>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3">Inscrições</p>
                <p class="mt-1 font-mono text-ink">{{ $tournament->registration_starts_at->format('d/m/Y H:i') }} a {{ $tournament->registration_ends_at->format('d/m/Y H:i') }}</p>
                <p class="text-xs text-ink-3">{{ $tournament->registrationOpen() ? 'Abertas agora' : 'Fechadas agora' }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-2 sm:col-span-2 sm:justify-end">
                @foreach ($transitions as $status)
                    <form method="POST" action="{{ route('tournaments.status', $tournament) }}" @if ($status === Tournament::STATUS_CANCELLED) onsubmit="return confirm('Cancelar o torneio? As inscrições pagas não são estornadas sozinhas: cancele cada uma em Inscrições, com ou sem estorno.')" @endif>
                        @csrf @method('PUT')
                        <input type="hidden" name="status" value="{{ $status }}">
                        @if ($actions[$status][1] === 'primary')
                            <x-primary-button>{{ $actions[$status][0] }}</x-primary-button>
                        @elseif ($actions[$status][1] === 'danger')
                            <x-danger-button>{{ $actions[$status][0] }}</x-danger-button>
                        @else
                            <x-secondary-button type="submit">{{ $actions[$status][0] }}</x-secondary-button>
                        @endif
                    </form>
                @endforeach
                @php $others = array_diff(array_keys(Tournament::STATUS_LABELS), $transitions, [$tournament->status]); @endphp
                @if ($others)
                    {{-- Fora do caminho normal (ex.: reabrir um encerrado): o Lara avisa e pede confirmação. --}}
                    <form method="POST" action="{{ route('tournaments.status', $tournament) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <select name="status" aria-label="Outra situação" class="h-10 rounded-xl border border-line-strong bg-surface px-3 text-sm text-ink">
                            @foreach ($others as $status)
                                <option value="{{ $status }}">{{ Tournament::STATUS_LABELS[$status] }}</option>
                            @endforeach
                        </select>
                        <x-secondary-button type="submit">Mudar</x-secondary-button>
                    </form>
                @endif
            </div>
            @if ($tournament->description)
                <p class="whitespace-pre-line text-sm text-ink-2 sm:col-span-4">{{ $tournament->description }}</p>
            @endif
        </section>

        @can(P::TORNEIOS_RESULTADOS)
            <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Lançamento de resultados pelo site</h2>
                        <p class="text-sm text-ink-3">Passe o código para a equipe que vai lançar os placares no site. Resultado lançado aqui no Lara trava o jogo para o site.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('tournaments.results.code.store', $tournament) }}" @if ($tournament->resultsCodeActive()) onsubmit="return confirm('Gerar um código novo? O atual deixa de valer.')" @endif>
                            @csrf
                            <x-secondary-button type="submit">{{ $tournament->resultsCodeActive() ? 'Gerar outro código' : 'Gerar código' }}</x-secondary-button>
                        </form>
                        @if ($tournament->resultsCodeActive())
                            <form method="POST" action="{{ route('tournaments.results.code.destroy', $tournament) }}" onsubmit="return confirm('Retirar o código? O site para de lançar resultado deste torneio.')">
                                @csrf @method('DELETE')
                                <x-danger-button>Retirar</x-danger-button>
                            </form>
                        @endif
                    </div>
                </div>
                @if (session('results_code'))
                    <p class="rounded-xl bg-ok-soft px-4 py-3 text-ok">Código: <span class="font-mono text-xl font-bold tracking-widest">{{ session('results_code') }}</span> <span class="block text-xs">Anote agora: ele não aparece de novo. Vale até {{ $tournament->results_code_expires_at?->format('d/m/Y H:i') }}.</span></p>
                @elseif ($tournament->resultsCodeActive())
                    <p class="text-sm text-ink-2">Há um código ativo, gerado em {{ $tournament->results_code_created_at?->format('d/m H:i') }} por {{ $tournament->resultsCodeCreator?->name ?? '—' }}, válido até {{ $tournament->results_code_expires_at->format('d/m/Y H:i') }}. Se ninguém anotou, gere outro.</p>
                @else
                    <p class="text-sm text-ink-3">Nenhum código ativo: o site não lança resultado deste torneio.</p>
                @endif
            </section>
        @endcan

        <section class="flex flex-col gap-4">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Categorias</h2>

            @if ($tournament->categories->isEmpty())
                <x-empty-state icon="tag">Nenhuma categoria ligada. Escolha abaixo do catálogo de {{ $tournament->placeGroup?->name }}.</x-empty-state>
            @else
                <div class="overflow-hidden rounded-card bg-surface shadow-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-subtle">
                                    <th class="{{ $th }}">Categoria</th>
                                    <th class="{{ $th }}">Preço</th>
                                    <th class="{{ $th }}">Vagas</th>
                                    <th class="{{ $th }}">Disputa</th>
                                    <th class="{{ $th }}">Inscritos</th>
                                    <th class="px-5 py-3.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($tournament->categories as $category)
                                    @php
                                        $paid = $count($category->id, Registration::STATUS_PAID);
                                        $pending = $count($category->id, Registration::STATUS_PENDING_PAYMENT);
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-3.5"><p class="font-semibold text-ink">{{ $category->name }}</p><p class="text-xs text-ink-3">{{ Category::describe($category->team_size, $category->gender, $category->min_age, $category->max_age) }}</p></td>
                                        <td class="px-5 py-3.5 font-mono">{{ $money($category->price) }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">{{ $category->max_registrations ?? 'Sem limite' }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">
                                            {{ Category::FORMATS[$category->format] ?? $category->format }}
                                            <span class="block text-xs text-ink-3">{{ Category::FORMATIONS[$category->team_formation] ?? '' }} · {{ $category->sets_to_win }} set(s) para vencer</span>
                                        </td>
                                        <td class="px-5 py-3.5 text-ink-2">
                                            {{ $paid }} paga(s)<span class="block text-xs text-ink-3">{{ $pending }} aguardando pagamento</span>
                                            <span class="block text-xs text-ink-3">{{ $category->complete_teams_count }}/{{ $category->active_teams_count }} time(s) completo(s)</span>
                                            @if ($category->stages_count)
                                                <span class="block text-xs text-ink-3">{{ $category->played_matches_count }}/{{ $category->playable_matches_count }} jogo(s) encerrado(s){{ $category->bracket_published_at ? '' : ' · não divulgado' }}</span>
                                            @else
                                                <span class="block text-xs text-ink-3">sem chaveamento</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                            @can(P::TORNEIOS_INSCRICOES)
                                                <x-secondary-button-a size="sm" href="{{ route('tournaments.teams.index', [$tournament, $category]) }}"><x-icon name="users" /> Times</x-secondary-button-a>
                                            @endcan
                                            <x-secondary-button-a size="sm" href="{{ route('tournaments.bracket.show', [$tournament, $category]) }}" title="{{ $category->stages_count ? ($category->bracket_published_at ? 'Divulgado no site' : 'Gerado, não divulgado') : 'Sem chaveamento' }}"><x-icon name="trophy" /> Chaveamento</x-secondary-button-a>
                                            <form method="POST" action="{{ route('tournaments.event-categories.destroy', [$tournament, $category]) }}" class="inline" onsubmit="return confirm('Tirar {{ addslashes($category->name) }} do torneio?')">
                                                @csrf @method('DELETE')
                                                <x-danger-button size="sm">Tirar</x-danger-button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr class="bg-subtle/40">
                                        <td colspan="6" class="px-5 py-3">
                                            <details>
                                                <summary class="cursor-pointer text-sm font-semibold text-ink-2">Editar preço, vagas e disputa</summary>
                                                <form method="POST" action="{{ route('tournaments.event-categories.update', [$tournament, $category]) }}" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end" data-link-form>
                                                    @csrf @method('PUT')
                                                    @include('tournaments.partials.link-fields', ['key' => 'c' . $category->id, 'link' => $category])
                                                    <div class="sm:col-span-4 sm:text-right"><x-primary-button>Salvar</x-primary-button></div>
                                                </form>
                                                <p class="mt-2 text-xs text-ink-3">Preço novo vale para as próximas inscrições; quem já se inscreveu mantém o valor. Mudar o formato com chaveamento gerado desfaz o chaveamento (o Lara avisa antes).</p>
                                            </details>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="rounded-card bg-surface p-5 shadow-card">
                <h3 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">Ligar categoria do catálogo</h3>
                @if ($templates->isEmpty())
                    <p class="text-sm text-ink-2">Todas as categorias ativas de {{ $tournament->placeGroup?->name }} já estão no torneio. Cadastre mais em <a href="{{ route('tournaments.categories.index', ['group' => $tournament->place_group_id]) }}">Categorias</a>.</p>
                @else
                    <form method="POST" action="{{ route('tournaments.event-categories.store', $tournament) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end" data-link-form>
                        @csrf
                        <div class="sm:col-span-2">
                            <x-input-label for="category_template_id" value="Categoria" />
                            <select id="category_template_id" name="category_template_id" class="mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink" required>
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }} — {{ $template->summary() }}</option>
                                @endforeach
                            </select>
                        </div>
                        @include('tournaments.partials.link-fields', ['key' => 'new', 'link' => null])
                        <div class="sm:col-span-4 sm:text-right"><x-primary-button>Ligar ao torneio</x-primary-button></div>
                    </form>
                @endif
            </div>
        </section>
    </x-page>

    <script>
        // Número de grupos e classificados só valem para grupos + mata-mata.
        document.querySelectorAll('[data-link-form]').forEach(function (form) {
            const format = form.querySelector('[data-format]');
            const apply = () => form.querySelectorAll('[data-groups-only]').forEach(el => el.classList.toggle('hidden', format.value !== 'groups_knockout'));
            format.addEventListener('change', apply);
            apply();
        });
    </script>
</x-app-layout>

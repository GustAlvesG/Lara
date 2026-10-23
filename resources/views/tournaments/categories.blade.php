{{-- Catálogo de categorias de torneio de um grupo de espaços (o "book" do
     grupo). O torneio copia a categoria ao usá-la: mudar ou remover aqui não
     mexe em torneio já criado. --}}
@php
    use App\Models\Tournament\TournamentCategoryTemplate as Template;

    $item = $editing;
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Categorias de torneio">
            Cada grupo de espaços tem o seu catálogo. A idade é conferida na data de início do torneio; o sexo vem do cadastro do sócio no MultiClubes.
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($groups->isEmpty())
            <x-empty-state icon="trophy">Nenhum grupo de espaços esportivo cadastrado. Cadastre em Reservas › Locais e Grupos.</x-empty-state>
        @else
            <form method="GET" action="{{ route('tournaments.categories.index') }}" class="flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-72">
                    <x-input-label for="group" value="Grupo de espaços" />
                    <select id="group" name="group" onchange="this.form.submit()" class="mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink">
                        @foreach ($groups as $option)
                            <option value="{{ $option->id }}" @selected($group && $group->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><x-secondary-button>Ver</x-secondary-button></noscript>
            </form>

            <section class="rounded-card bg-surface p-5 shadow-card">
                <h2 class="mb-4 text-sm font-black uppercase tracking-widest text-ink-3">{{ $item ? 'Editar ' . $item->name : 'Nova categoria em ' . $group->name }}</h2>
                <form method="POST" action="{{ $item ? route('tournaments.categories.update', $item) : route('tournaments.categories.store') }}" class="grid grid-cols-1 gap-4 sm:grid-cols-6 sm:items-end">
                    @csrf
                    @if ($item) @method('PUT') @else <input type="hidden" name="place_group_id" value="{{ $group->id }}"> @endif
                    <div class="sm:col-span-2"><x-input-label for="name" value="Nome" /><x-text-input id="name" name="name" class="mt-1" :value="old('name', $item->name ?? '')" required maxlength="255" placeholder="Ex.: Feminina B" /></div>
                    <div><x-input-label for="team_size" value="Pessoas por equipe" /><x-text-input type="number" min="1" max="50" id="team_size" name="team_size" class="mt-1" :value="old('team_size', $item->team_size ?? 2)" required /></div>
                    <div>
                        <x-input-label for="gender" value="Sexo" />
                        <select id="gender" name="gender" class="mt-1 h-11 w-full rounded-xl border border-line-strong bg-surface px-3 text-ink">
                            @foreach (Template::GENDERS as $value => $label)
                                <option value="{{ $value }}" @selected(old('gender', $item->gender ?? Template::GENDER_ANY) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div><x-input-label for="min_age" value="Idade mínima" /><x-text-input type="number" min="0" max="120" id="min_age" name="min_age" class="mt-1" :value="old('min_age', $item->min_age ?? '')" placeholder="Sem limite" /></div>
                    <div><x-input-label for="max_age" value="Idade máxima" /><x-text-input type="number" min="0" max="120" id="max_age" name="max_age" class="mt-1" :value="old('max_age', $item->max_age ?? '')" placeholder="Sem limite" /></div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-ink sm:col-span-3">
                        <input type="checkbox" name="active" value="1" @checked(old('active', $item->active ?? true)) class="h-4 w-4 rounded border-line-strong text-grena-ink">
                        Disponível para novos torneios
                    </label>
                    <div class="flex gap-2 sm:col-span-3 sm:justify-end">
                        @if ($item)<x-secondary-button-a href="{{ route('tournaments.categories.index', ['group' => $group->id]) }}">Cancelar</x-secondary-button-a>@endif
                        <x-primary-button>{{ $item ? 'Salvar' : 'Adicionar' }}</x-primary-button>
                    </div>
                </form>
            </section>

            @if ($templates->isEmpty())
                <x-empty-state icon="trophy">Nenhuma categoria em {{ $group->name }}.</x-empty-state>
            @else
                <x-search-bar mode="client" target="#categorias" id="busca-categorias" placeholder="Buscar categoria" />
                <div class="overflow-hidden rounded-card bg-surface shadow-card">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-line bg-subtle">
                                    <th class="{{ $th }}">Categoria</th>
                                    <th class="{{ $th }}">Equipe</th>
                                    <th class="{{ $th }}">Sexo</th>
                                    <th class="{{ $th }}">Idade</th>
                                    <th class="{{ $th }} text-center">Situação</th>
                                    <th class="px-5 py-3.5"></th>
                                </tr>
                            </thead>
                            <tbody id="categorias" class="divide-y divide-line">
                                @foreach ($templates as $template)
                                    <tr data-search="{{ $template->name }} {{ $template->summary() }}">
                                        <td class="px-5 py-3.5 font-semibold text-ink">{{ $template->name }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">{{ $template->team_size === 1 ? 'Individual' : $template->team_size . ' pessoas' }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">{{ Template::GENDERS[$template->gender] ?? $template->gender }}</td>
                                        <td class="px-5 py-3.5 text-ink-2">
                                            @if ($template->min_age !== null && $template->max_age !== null) {{ $template->min_age }} a {{ $template->max_age }} anos
                                            @elseif ($template->min_age !== null) {{ $template->min_age }}+ anos
                                            @elseif ($template->max_age !== null) até {{ $template->max_age }} anos
                                            @else Livre
                                            @endif
                                        </td>
                                        <td class="px-5 py-3.5 text-center"><x-pill :kind="$template->active ? 'ok' : 'off'">{{ $template->active ? 'Ativa' : 'Inativa' }}</x-pill></td>
                                        <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                            <x-secondary-button-a size="sm" href="{{ route('tournaments.categories.index', ['group' => $group->id, 'edit' => $template->id]) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
                                            <form method="POST" action="{{ route('tournaments.categories.destroy', $template) }}" class="inline" onsubmit="return confirm('Remover {{ addslashes($template->name) }} do catálogo? Torneios que já usam a categoria não mudam.')">
                                                @csrf @method('DELETE')
                                                <x-danger-button size="sm">Remover</x-danger-button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif
    </x-page>
</x-app-layout>

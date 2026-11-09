{{--
    Importar a lista a partir dos sócios do MultiClubes (permissão
    `disparo-emails.multiclubes`). Os tipos de título e a contagem vêm sob
    demanda (rotas email-campaigns.multiclubes.*): a tela da campanha não
    consulta o MultiClubes ao abrir. Variáveis da tela: $campaign, $counts,
    $selectClass, $hint.
--}}
@php
    use App\Models\EmailCampaign\EmailCampaign;
    use App\Services\MultiClubes\MemberMailingFilter;

    $mcConfig = [
        'typesUrl' => route('email-campaigns.multiclubes.types'),
        'countUrl' => route('email-campaigns.multiclubes.count'),
        'selectedTypes' => array_map('intval', (array) old('tipos', [])),
        'open' => old('vinculo') !== null,
    ];
    $mcTitleStatuses = array_map('intval', (array) old('situacao_titulo', old('vinculo') !== null ? [] : [0]));
    $mcMemberStatuses = array_map('intval', (array) old('situacao_socio', old('vinculo') !== null ? [] : [0]));
    $mcBox = 'rounded-xl border border-line bg-surface p-3';
    $mcLegend = 'mb-2 text-xs font-bold text-ink-3';
    $mcCheck = 'rounded border-line-strong text-grena focus:ring-grena-tint';
@endphp

<form method="POST" action="{{ route('email-campaigns.multiclubes.import', $campaign) }}"
      x-data="multiclubesImport({{ Js::from($mcConfig) }})" x-ref="form"
      class="mb-5 rounded-xl border border-dashed border-line-strong bg-subtle p-4"
      @submit="if (!confirm(confirmText())) $event.preventDefault()">
    @csrf

    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <p class="text-sm font-bold text-ink">Importar sócios do MultiClubes</p>
            <p class="text-xs text-ink-2">Monte a lista com os associados, sem planilha: escolha os filtros, confira quantos são e importe.</p>
        </div>
        <x-secondary-button size="sm" type="button" @click="toggle()" x-show="!open"><x-icon name="users" class="h-3.5 w-3.5" /> Escolher sócios</x-secondary-button>
    </div>

    <div x-show="open" x-cloak class="mt-4 flex flex-col gap-3">
        {{-- Tipo de título --}}
        <fieldset class="{{ $mcBox }}">
            <legend class="sr-only">Tipo de título</legend>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-bold text-ink-3">Tipo de título</p>
                <div class="flex gap-3 text-xs">
                    <button type="button" class="font-bold text-grena-ink hover:underline" @click="selectAll()">Marcar todos</button>
                    <button type="button" class="font-bold text-grena-ink hover:underline" @click="selected = []">Limpar</button>
                </div>
            </div>
            <p class="mb-2 text-xs text-ink-3">Nenhum marcado = todos os tipos de sócio, sem os especiais (funcionários, escolinha não sócio, terceirizados…). Os especiais só entram marcados.</p>
            <x-text-input type="search" x-model="search" placeholder="Buscar tipo" class="mb-2" aria-label="Buscar tipo de título" />
            <p x-show="loading" class="text-xs text-ink-3">Carregando os tipos do MultiClubes…</p>
            <p x-show="typesError" x-text="typesError" class="text-xs text-danger"></p>
            <div class="max-h-56 overflow-y-auto">
                <template x-for="type in filteredTypes()" :key="type.id">
                    <label class="flex items-center gap-2 py-1 text-sm text-ink">
                        <input type="checkbox" name="tipos[]" :value="type.id" x-model.number="selected" class="{{ $mcCheck }}">
                        <span x-text="type.name"></span>
                        <span class="text-xs text-ink-3" x-text="`${format(type.active_titles)} título(s) ativo(s)`"></span>
                        <x-pill kind="warn" :icon="false" x-show="type.special">especial</x-pill>
                    </label>
                </template>
            </div>
        </fieldset>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <fieldset class="{{ $mcBox }}">
                <legend class="{{ $mcLegend }}">Situação do título</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach (MemberMailingFilter::TITLE_STATUSES as $value => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" name="situacao_titulo[]" value="{{ $value }}" @checked(in_array($value, $mcTitleStatuses, true)) @change="stale()" class="{{ $mcCheck }}"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="{{ $hint }}">Nenhuma marcada = qualquer situação.</p>
            </fieldset>

            <fieldset class="{{ $mcBox }}">
                <legend class="{{ $mcLegend }}">Situação do sócio</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach (MemberMailingFilter::MEMBER_STATUSES as $value => $label)
                        <label class="inline-flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" name="situacao_socio[]" value="{{ $value }}" @checked(in_array($value, $mcMemberStatuses, true)) @change="stale()" class="{{ $mcCheck }}"> {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="{{ $hint }}">Nenhuma marcada = qualquer situação.</p>
            </fieldset>

            <div class="{{ $mcBox }}">
                <x-input-label for="mc_vinculo" class="mb-1.5">Titular ou dependente</x-input-label>
                <select name="vinculo" id="mc_vinculo" class="{{ $selectClass }}" @change="stale()">
                    @foreach (MemberMailingFilter::LINKS as $value => $label)
                        <option value="{{ $value }}" @selected(old('vinculo', MemberMailingFilter::LINK_ALL) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="{{ $mcBox }}">
                <x-input-label for="mc_sexo" class="mb-1.5">Sexo</x-input-label>
                <select name="sexo" id="mc_sexo" class="{{ $selectClass }}" @change="stale()">
                    <option value="">Todos</option>
                    @foreach (MemberMailingFilter::SEXES as $value => $label)
                        <option value="{{ $value }}" @selected((string) old('sexo') === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="{{ $mcBox }} sm:col-span-2">
                <p class="{{ $mcLegend }}">Idade (anos completos hoje)</p>
                <div class="flex flex-wrap items-center gap-2 text-sm text-ink-2">
                    <label for="mc_idade_min">de</label>
                    <x-text-input type="number" min="0" max="130" name="idade_min" id="mc_idade_min" value="{{ old('idade_min') }}" class="w-24" @input="stale()" />
                    <label for="mc_idade_max">até</label>
                    <x-text-input type="number" min="0" max="130" name="idade_max" id="mc_idade_max" value="{{ old('idade_max') }}" class="w-24" @input="stale()" />
                    <span>anos</span>
                </div>
                <p class="{{ $hint }}">Em branco = sem limite. "Até 17" pega quem ainda não fez 18.</p>
                <x-input-error :messages="$errors->get('idade_max')" class="mt-1" />
            </div>
        </div>

        <x-input-error :messages="array_merge($errors->get('tipos.*'), $errors->get('situacao_titulo.*'), $errors->get('situacao_socio.*'), $errors->get('vinculo'), $errors->get('sexo'), $errors->get('idade_min'))" />

        {{-- Contagem e importação --}}
        <div class="flex flex-col gap-3 border-t border-line pt-3 sm:flex-row sm:items-center">
            <x-secondary-button size="sm" type="button" @click="count()" x-bind:disabled="counting"><x-icon name="search" class="h-3.5 w-3.5" /> <span x-text="counting ? 'Contando…' : 'Contar'">Contar</span></x-secondary-button>
            @if ($campaign->status === EmailCampaign::STATUS_DRAFT && $counts['total'] > 0)
                <select name="mode" class="{{ $selectClass }}" aria-label="O que fazer com a lista atual">
                    <option value="append">Acrescentar à lista atual</option>
                    <option value="replace">Substituir a lista atual</option>
                </select>
            @else
                <input type="hidden" name="mode" value="append">
            @endif
            <x-primary-button size="sm"><x-icon name="download" class="h-3.5 w-3.5" /> Importar sócios</x-primary-button>
        </div>

        <p x-show="result" x-cloak class="text-sm text-ink">
            <b x-text="format(result?.members)"></b> sócio(s) nesses filtros,
            <b x-text="format(result?.with_email)"></b> com e-mail —
            <b x-text="format(result?.distinct_emails)"></b> e-mail(s) diferente(s) entram na lista.
        </p>
        <p x-show="countError" x-cloak x-text="countError" class="text-sm text-danger"></p>
        <p class="text-xs text-ink-3">
            Sócio sem e-mail no MultiClubes fica de fora. Titular e dependente com o mesmo e-mail recebem um só (fica o do titular).
            Variáveis da lista: <span class="font-mono">&#123;&#123;nome&#125;&#125; &#123;&#123;primeiro_nome&#125;&#125; &#123;&#123;titulo&#125;&#125; &#123;&#123;tipo_titulo&#125;&#125; &#123;&#123;vinculo&#125;&#125; &#123;&#123;parentesco&#125;&#125; &#123;&#123;idade&#125;&#125; &#123;&#123;sexo&#125;&#125;</span>.
        </p>
    </div>
</form>

@once
    <script>
        function multiclubesImport(config) {
            return {
                open: config.open,
                loading: false,
                types: [],
                typesError: '',
                selected: config.selectedTypes,
                search: '',
                counting: false,
                result: null,
                countError: '',

                init() {
                    if (this.open) this.loadTypes();
                    this.$watch('selected', () => this.stale());
                },

                toggle() {
                    this.open = true;
                    this.loadTypes();
                },

                async loadTypes() {
                    if (this.types.length || this.loading) return;
                    this.loading = true;
                    this.typesError = '';
                    try {
                        const response = await fetch(config.typesUrl, { headers: { 'Accept': 'application/json' } });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Não foi possível carregar os tipos de título.');
                        this.types = data.types;
                    } catch (e) {
                        this.typesError = e.message || 'Não foi possível carregar os tipos de título.';
                    } finally {
                        this.loading = false;
                    }
                },

                filteredTypes() {
                    const term = this.search.trim().toLowerCase();
                    return term ? this.types.filter(t => t.name.toLowerCase().includes(term)) : this.types;
                },

                selectAll() {
                    this.selected = this.types.filter(t => !t.special).map(t => t.id);
                },

                stale() {
                    this.result = null;
                    this.countError = '';
                },

                async count() {
                    this.counting = true;
                    this.stale();
                    try {
                        const response = await fetch(config.countUrl, {
                            method: 'POST',
                            headers: { 'Accept': 'application/json' },
                            body: new FormData(this.$refs.form),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            const first = data.errors ? Object.values(data.errors)[0][0] : null;
                            throw new Error(first || data.message || 'Não foi possível contar.');
                        }
                        this.result = data;
                    } catch (e) {
                        this.countError = e.message || 'Não foi possível contar.';
                    } finally {
                        this.counting = false;
                    }
                },

                confirmText() {
                    return this.result
                        ? `Importar ${this.format(this.result.distinct_emails)} e-mail(s) de sócios do MultiClubes para esta campanha?`
                        : 'Importar os sócios do MultiClubes desses filtros para esta campanha?';
                },

                format(value) {
                    return Number(value || 0).toLocaleString('pt-BR');
                },
            };
        }
    </script>
@endonce

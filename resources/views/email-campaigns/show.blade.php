{{--
    Tela da campanha: andamento, enviar/agendar/pausar, e-mail de teste, a
    lista de destinatários e a prévia. O andamento se atualiza sozinho
    (a cada 5 s) enquanto a campanha está agendada ou enviando — mas o envio
    não depende desta tela aberta: quem manda é o scheduler.
--}}
@php
    use App\Models\EmailCampaign\EmailCampaign;
    use App\Models\EmailCampaign\EmailCampaignRecipient;

    $th = 'px-4 py-3 text-left text-xs font-bold text-ink-3';
    $td = 'px-4 py-2.5';
    $card = 'rounded-card bg-surface p-5 shadow-card sm:p-6';
    $hint = 'mt-1.5 text-xs text-ink-3';
    $selectClass = 'h-11 w-full rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint sm:w-auto';
    $minutesLeft = $perMinute > 0 ? (int) ceil($counts['pending'] / $perMinute) : 0;
    $rejected = session('import_rejected');
@endphp

<x-app-layout :bootstrap-grid="false">
    <x-page wide>
        <x-page-title :title="$campaign->name" :back="route('email-campaigns.index')">
            {{ $campaign->subject }}
            <x-slot:actions>
                @if ($campaign->isEditable())
                    <x-secondary-button-a href="{{ route('email-campaigns.edit', $campaign) }}"><x-icon name="pencil" /> Editar</x-secondary-button-a>
                @endif
                <form method="POST" action="{{ route('email-campaigns.duplicate', $campaign) }}">
                    @csrf
                    <x-secondary-button type="submit"><x-icon name="doc" /> Duplicar</x-secondary-button>
                </form>
                @if ($campaign->canDelete())
                    <form method="POST" action="{{ route('email-campaigns.destroy', $campaign) }}"
                          onsubmit="return confirm('Excluir a campanha e toda a lista de destinatários? Não tem volta.')">
                        @csrf @method('DELETE')
                        <x-danger-button><x-icon name="trash" /> Excluir</x-danger-button>
                    </form>
                @endif
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if ($unknownVariables && $campaign->isEditable())
            <div class="rounded-2xl bg-warn-soft px-5 py-4 text-sm text-warn" role="alert">
                O texto usa variáveis que a lista não tem:
                <span class="font-mono">@foreach ($unknownVariables as $key)&#123;&#123;{{ $key }}&#125;&#125;@if (! $loop->last), @endif @endforeach</span>.
                Elas sairiam vazias — o envio fica bloqueado até corrigir o texto ou importar uma lista com essas colunas.
            </div>
        @endif

        {{-- Andamento --}}
        <section class="{{ $card }}"
                 x-data="campaignProgress({{ Js::from([
                     'url' => route('email-campaigns.progress', $campaign),
                     'active' => $campaign->isActive(),
                     'status' => $campaign->status,
                     'counts' => $counts,
                     'perMinute' => $perMinute,
                 ]) }})">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <x-pill :kind="$campaign->statusPill()">{{ $campaign->statusLabel() }}</x-pill>
                    @if ($campaign->status === EmailCampaign::STATUS_SCHEDULED)
                        <span class="text-sm text-ink-2">começa em {{ $campaign->scheduled_at?->format('d/m/Y \à\s H:i') }}</span>
                    @elseif ($campaign->started_at)
                        <span class="text-sm text-ink-2">começou em {{ $campaign->started_at->format('d/m/Y H:i') }}</span>
                    @endif
                    @if ($campaign->finished_at)
                        <span class="text-sm text-ink-2">· terminou em {{ $campaign->finished_at->format('d/m/Y H:i') }}</span>
                    @endif
                </div>
                <p class="text-xs text-ink-3" x-show="active" x-cloak>
                    Atualiza sozinho. Pode fechar esta tela: o envio continua no servidor.
                    <template x-if="counts.pending > 0"><span>Faltam cerca de <span x-text="minutesLeft()"></span> min (até {{ $perMinute }} e-mails por minuto).</span></template>
                </p>
            </div>

            <div class="mb-3 h-2.5 w-full overflow-hidden rounded-full bg-subtle">
                <div class="h-full rounded-full bg-grena transition-all" :style="`width: ${percent()}%`" style="width: {{ $counts['total'] ? floor(($counts['sent'] + $counts['failed'] + $counts['skipped']) * 100 / $counts['total']) : 0 }}%"></div>
            </div>

            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                @foreach ([
                    'total' => ['Destinatários', 'text-ink'],
                    'sent' => ['Enviados', 'text-ok'],
                    'pending' => ['Na fila', 'text-ink-2'],
                    'failed' => ['Falharam', 'text-danger'],
                    'skipped' => ['Descadastrados', 'text-warn'],
                ] as $key => [$label, $color])
                    <div class="rounded-xl bg-subtle px-4 py-3">
                        <dt class="text-xs font-bold text-ink-3">{{ $label }}</dt>
                        <dd class="mt-0.5 font-mono text-xl font-semibold {{ $color }}" x-text="format(counts.{{ $key }})">{{ number_format($counts[$key], 0, ',', '.') }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($campaign->last_error)
                <p class="mt-4 rounded-xl bg-warn-soft px-4 py-3 text-sm text-warn">{{ $campaign->last_error }}</p>
            @endif

            <div class="mt-5 flex flex-wrap gap-2 border-t border-line pt-5">
                @if ($campaign->canPause())
                    <form method="POST" action="{{ route('email-campaigns.pause', $campaign) }}">@csrf
                        <x-secondary-button type="submit"><x-icon name="clock" /> Pausar</x-secondary-button>
                    </form>
                @endif
                @if ($campaign->canResume())
                    <form method="POST" action="{{ route('email-campaigns.resume', $campaign) }}">@csrf
                        <x-primary-button><x-icon name="arrow-right" /> Retomar o envio</x-primary-button>
                    </form>
                @endif
                @if ($campaign->canUnschedule())
                    <form method="POST" action="{{ route('email-campaigns.unschedule', $campaign) }}">@csrf
                        <x-secondary-button type="submit"><x-icon name="calendar" /> Desfazer agendamento</x-secondary-button>
                    </form>
                @endif
                @if ($counts['failed'] > 0 && in_array($campaign->status, [EmailCampaign::STATUS_DONE, EmailCampaign::STATUS_SENDING, EmailCampaign::STATUS_PAUSED], true))
                    <form method="POST" action="{{ route('email-campaigns.retry', $campaign) }}"
                          onsubmit="return confirm('Devolver à fila os {{ $counts['failed'] }} envios que falharam?')">@csrf
                        <x-secondary-button type="submit"><x-icon name="history" /> Reenviar as que falharam</x-secondary-button>
                    </form>
                @endif
                @if ($campaign->canCancel())
                    <form method="POST" action="{{ route('email-campaigns.cancel', $campaign) }}"
                          onsubmit="return confirm('Cancelar a campanha? Quem ainda está na fila não vai receber, e não dá para retomar.')">@csrf
                        <x-danger-button><x-icon name="ban" /> Cancelar campanha</x-danger-button>
                    </form>
                @endif
            </div>
        </section>

        @if ($campaign->canStart())
            {{-- Enviar --}}
            <section class="{{ $card }}" x-data="{ when: '{{ old('when', 'now') }}' }">
                <h3 class="mb-1 text-base font-bold text-ink">Enviar</h3>
                <p class="mb-4 text-sm text-ink-2">Mande um teste para você antes. Depois de começar, o texto e o remetente só mudam com o envio pausado.</p>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <form method="POST" action="{{ route('email-campaigns.test', $campaign) }}" class="flex flex-col gap-2">
                        @csrf
                        <x-input-label for="test_email">E-mail de teste</x-input-label>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <x-text-input type="email" name="test_email" id="test_email" required value="{{ old('test_email', auth()->user()->email) }}" />
                            <x-secondary-button type="submit"><x-icon name="mail" /> Mandar teste</x-secondary-button>
                        </div>
                        <p class="text-xs text-ink-3">Sai na hora, com "[TESTE]" no assunto e os dados do primeiro destinatário da lista.</p>
                        <x-input-error :messages="$errors->get('test_email')" />
                    </form>

                    <form method="POST" action="{{ route('email-campaigns.start', $campaign) }}" class="flex flex-col gap-3"
                          onsubmit="return confirm(this.when.value === 'now'
                              ? 'Começar a enviar para {{ number_format($counts['pending'], 0, ',', '.') }} destinatário(s) agora?'
                              : 'Agendar o envio para {{ number_format($counts['pending'], 0, ',', '.') }} destinatário(s)?')">
                        @csrf
                        <fieldset class="flex flex-wrap gap-4 text-sm text-ink">
                            <legend class="mb-2 text-sm font-bold text-ink">Quando</legend>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="when" value="now" x-model="when" class="text-grena focus:ring-grena-tint"> Agora</label>
                            <label class="inline-flex items-center gap-2"><input type="radio" name="when" value="schedule" x-model="when" class="text-grena focus:ring-grena-tint"> Agendar</label>
                        </fieldset>
                        <div x-show="when === 'schedule'" x-cloak>
                            <x-input-label for="scheduled_at" class="mb-1.5">Data e hora</x-input-label>
                            <x-text-input type="datetime-local" name="scheduled_at" id="scheduled_at" value="{{ old('scheduled_at') }}" class="sm:max-w-xs" />
                            <x-input-error :messages="$errors->get('scheduled_at')" class="mt-1" />
                        </div>
                        <div>
                            <x-primary-button :disabled="$counts['pending'] === 0 || $unknownVariables">
                                <x-icon name="mail" />
                                <span x-text="when === 'now' ? 'Enviar para {{ number_format($counts['pending'], 0, ',', '.') }}' : 'Agendar envio'">Enviar</span>
                            </x-primary-button>
                            @if ($counts['pending'] === 0)
                                <p class="{{ $hint }}">Importe a lista de destinatários para liberar o envio.</p>
                            @else
                                <p class="{{ $hint }}">Leva cerca de {{ $minutesLeft }} min ({{ $perMinute }} e-mails por minuto).</p>
                            @endif
                        </div>
                    </form>
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-5">
            {{-- Destinatários --}}
            <section class="{{ $card }} min-w-0 xl:col-span-3">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-ink">Destinatários</h3>
                        @if ($campaign->list_name)
                            <p class="text-xs text-ink-3">Lista: {{ $campaign->list_name }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($counts['total'] > 0)
                            <x-secondary-button-a size="sm" href="{{ route('email-campaigns.recipients.export', $campaign) }}"><x-icon name="download" class="h-3.5 w-3.5" /> Baixar resultado (.csv)</x-secondary-button-a>
                        @endif
                        @if ($campaign->status === EmailCampaign::STATUS_DRAFT && $counts['total'] > 0)
                            <form method="POST" action="{{ route('email-campaigns.recipients.clear', $campaign) }}"
                                  onsubmit="return confirm('Apagar a lista inteira desta campanha?')">
                                @csrf @method('DELETE')
                                <x-danger-button size="sm"><x-icon name="trash" class="h-3.5 w-3.5" /> Apagar lista</x-danger-button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($campaign->isEditable())
                    <form method="POST" action="{{ route('email-campaigns.recipients.import', $campaign) }}" enctype="multipart/form-data"
                          x-data="{ fileName: '' }" class="mb-5 rounded-xl border border-dashed border-line-strong bg-subtle p-4">
                        @csrf
                        <p class="mb-2 text-sm font-bold text-ink">Importar lista (.csv ou .xlsx)</p>
                        <ul class="mb-3 list-inside list-disc space-y-1 text-xs text-ink-2">
                            <li>Precisa de uma coluna <b>E-mail</b> (ou só os endereços, um por linha). <b>Nome</b> é opcional; as outras colunas viram variáveis do texto.</li>
                            <li>E-mail inválido ou repetido fica de fora e é avisado; o resto entra. Até {{ number_format($maxRecipients, 0, ',', '.') }} por campanha.</li>
                            <li>Modelo: <a class="font-bold text-grena-ink hover:underline" href="{{ route('email-campaigns.list-template', 'xlsx') }}">.xlsx</a> ou <a class="font-bold text-grena-ink hover:underline" href="{{ route('email-campaigns.list-template', 'csv') }}">.csv</a>.</li>
                        </ul>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <input type="file" name="list" accept=".csv,.txt,.xlsx,.xls" required @change="fileName = $event.target.files[0]?.name ?? ''"
                                   class="min-w-0 flex-1 cursor-pointer text-sm text-ink-2 file:mr-3 file:rounded-lg file:border-0 file:bg-surface file:px-3 file:py-2 file:text-sm file:font-bold file:text-ink">
                            @if ($campaign->status === EmailCampaign::STATUS_DRAFT && $counts['total'] > 0)
                                <select name="mode" class="{{ $selectClass }}" aria-label="O que fazer com a lista atual">
                                    <option value="append">Acrescentar à lista atual</option>
                                    <option value="replace">Substituir a lista atual</option>
                                </select>
                            @else
                                <input type="hidden" name="mode" value="append">
                            @endif
                            <x-primary-button size="sm" x-bind:disabled="!fileName">Importar</x-primary-button>
                        </div>
                        <x-input-error :messages="$errors->get('list')" class="mt-1" />
                        @if ($campaign->status === EmailCampaign::STATUS_PAUSED)
                            <p class="{{ $hint }}">Com a campanha pausada, a lista só pode ser acrescentada: quem já recebeu não recebe de novo.</p>
                        @endif
                    </form>
                @endif

                @if ($rejected)
                    <div class="mb-5 rounded-xl border border-danger/40 bg-danger-soft p-4 text-sm text-danger">
                        <p class="mb-1 font-bold">{{ $rejected['count'] }} linha(s) ficaram de fora:</p>
                        <ul class="max-h-48 list-inside list-disc space-y-0.5 overflow-y-auto">
                            @foreach ($rejected['samples'] as $sampleLine)
                                <li>{{ $sampleLine }}</li>
                            @endforeach
                            @if ($rejected['count'] > count($rejected['samples']))
                                <li>… e mais {{ $rejected['count'] - count($rejected['samples']) }}.</li>
                            @endif
                        </ul>
                    </div>
                @endif

                @if ($counts['total'] > 0)
                    <x-search-bar placeholder="Buscar e-mail ou nome" :filters="['situacao']">
                        <x-slot:controls>
                            <select name="situacao" class="{{ $selectClass }}" aria-label="Situação do envio">
                                <option value="">Todas as situações</option>
                                @foreach (EmailCampaignRecipient::STATUS_LABELS as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['situacao'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-slot:controls>
                    </x-search-bar>

                    <div class="mt-3 overflow-x-auto rounded-xl border border-line">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-line">
                                    <th class="{{ $th }}">E-mail</th>
                                    <th class="{{ $th }}">Nome</th>
                                    <th class="{{ $th }}">Situação</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recipients as $recipient)
                                    <tr class="border-b border-line last:border-0">
                                        <td class="{{ $td }} font-mono text-[13px] text-ink">{{ $recipient->email }}</td>
                                        <td class="{{ $td }} text-ink-2">{{ $recipient->name ?? '—' }}</td>
                                        <td class="{{ $td }}">
                                            <x-pill :kind="$recipient->statusPill()">{{ $recipient->statusLabel() }}</x-pill>
                                            @if ($recipient->sent_at)
                                                <span class="ml-1 text-xs text-ink-3">{{ $recipient->sent_at->format('d/m H:i') }}</span>
                                            @endif
                                            @if ($recipient->error)
                                                <p class="mt-1 max-w-md text-xs text-danger">{{ $recipient->error }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="{{ $td }} text-ink-3">Ninguém com esse filtro.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">{{ $recipients->links() }}</div>
                @else
                    <x-empty-state icon="users">A lista ainda não foi importada.</x-empty-state>
                @endif
            </section>

            {{-- Prévia --}}
            <section class="{{ $card }} min-w-0 xl:col-span-2" x-data="{ expanded: false, width: 'desktop' }">
                {{-- Expandida, a prévia cobre a tela inteira; Esc ou "Fechar" voltam. --}}
                <div class="flex flex-col" :class="expanded && 'fixed inset-0 z-50 bg-surface p-4 sm:p-6'" @keydown.escape.window="expanded = false">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h3 class="mb-1 text-base font-bold text-ink">Prévia</h3>
                            <p class="text-xs text-ink-3">Com os dados do primeiro destinatário da lista.</p>
                        </div>
                        <div class="flex gap-1">
                            <template x-if="expanded">
                                <div class="flex gap-1" role="group" aria-label="Largura da prévia">
                                    <x-secondary-button size="sm" x-bind:class="width === 'desktop' && '!border-grena text-grena-ink'" @click="width = 'desktop'"><x-icon name="monitor" class="h-3.5 w-3.5" /> Computador</x-secondary-button>
                                    <x-secondary-button size="sm" x-bind:class="width === 'mobile' && '!border-grena text-grena-ink'" @click="width = 'mobile'">Celular</x-secondary-button>
                                </div>
                            </template>
                            <x-secondary-button size="sm" @click="expanded = !expanded" x-bind:aria-pressed="expanded.toString()">
                                <x-icon name="eye" class="h-3.5 w-3.5" /> <span x-text="expanded ? 'Fechar' : 'Expandir'">Expandir</span>
                            </x-secondary-button>
                        </div>
                    </div>
                    <div class="flex justify-center overflow-hidden rounded-xl border border-line-strong bg-[#f4f1ee]" :class="expanded ? 'min-h-0 flex-1' : 'h-[640px]'">
                        <iframe src="{{ route('email-campaigns.preview', $campaign) }}" title="Prévia do e-mail" loading="lazy"
                                sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                class="h-full bg-white transition-all" :class="expanded && width === 'mobile' ? 'w-[375px]' : 'w-full'"></iframe>
                    </div>
                </div>
                <dl class="mt-3 space-y-1 text-xs text-ink-2">
                    <div><dt class="inline font-bold text-ink">De:</dt> <dd class="inline">{{ $campaign->from_name ? $campaign->from_name . ' <' . $campaign->from_address . '>' : $campaign->from_address }}</dd></div>
                    <div><dt class="inline font-bold text-ink">Responder para:</dt> <dd class="inline">{{ $campaign->reply_to ?: $campaign->from_address }}</dd></div>
                    <div><dt class="inline font-bold text-ink">Criada por:</dt> <dd class="inline">{{ $campaign->author?->name ?? '—' }} em {{ $campaign->created_at?->format('d/m/Y H:i') }}</dd></div>
                </dl>
            </section>
        </div>
    </x-page>

    <script>
        function campaignProgress(config) {
            return {
                active: config.active,
                status: config.status,
                counts: config.counts,
                timer: null,

                init() {
                    if (this.active) {
                        this.timer = setInterval(() => this.refresh(), 5000);
                    }
                },

                async refresh() {
                    try {
                        const response = await fetch(config.url, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) return;
                        const data = await response.json();

                        this.counts = data.counts;

                        // Mudou a situação (terminou, pausou sozinha): recarrega para os botões certos.
                        if (data.status !== this.status) {
                            clearInterval(this.timer);
                            window.location.reload();
                        }
                    } catch (e) {
                        // Sem rede agora; tenta de novo no próximo ciclo.
                    }
                },

                percent() {
                    const done = this.counts.sent + this.counts.failed + this.counts.skipped;
                    return this.counts.total ? Math.floor(done * 100 / this.counts.total) : 0;
                },

                minutesLeft() {
                    return config.perMinute ? Math.ceil(this.counts.pending / config.perMinute) : 0;
                },

                format(value) {
                    return Number(value || 0).toLocaleString('pt-BR');
                },
            };
        }
    </script>
</x-app-layout>

{{--
    Assinaturas → Diretoria. As áreas da diretoria e quem responde por cada uma
    (titular e substitutos). É daqui que sai a lista "Diretor do clube" do
    documento; o diretor escolhido assina no tablet com matrícula e PIN.
    Ver DirectorAreaController.
--}}
@php
    $campo = 'w-full rounded-xl border-line-strong shadow-card focus:border-grena focus:ring-grena-tint';
    $campoMenor = 'w-full rounded-lg border-line-strong text-sm';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Diretoria">
            As áreas da diretoria e quem assina por cada uma. Ao criar um documento, o atendente escolhe o titular ou um
            substituto; o diretor assina no tablet com a matrícula e o PIN do Lara, ou pelo gov.br.
            Só entra aqui quem é do setor <span class="font-semibold text-ink">{{ \App\Models\User::DIRECTORS_SECTOR }}</span>,
            está ativo e tem matrícula.
        </x-page-title>

        @include('partials.alerts')

        <form action="{{ route('signature-directors.store') }}" method="POST" class="rounded-card bg-surface p-6 shadow-card">
            @csrf
            <h3 class="mb-4 font-display text-base font-semibold text-ink">Nova área</h3>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm font-bold text-ink">Nome da área</label>
                    <input type="text" name="name" id="name" maxlength="120" required
                           value="{{ old('name') }}" placeholder="Diretoria Financeira" class="{{ $campo }}">
                    <p class="mt-1 text-xs text-ink-2">Sai impresso sob o nome de quem assina.</p>
                    @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="mb-1 block text-sm font-bold text-ink">Descrição (opcional)</label>
                    <input type="text" name="description" id="description" maxlength="255"
                           value="{{ old('description') }}" class="{{ $campo }}">
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <x-primary-button>Criar área</x-primary-button>
            </div>
        </form>

        @if($candidates->isEmpty())
            <p class="rounded-xl bg-subtle p-4 text-sm text-ink-2">
                Nenhum usuário ativo com matrícula no setor {{ \App\Models\User::DIRECTORS_SECTOR }}. Vincule os diretores em
                Setores antes de pô-los numa área.
            </p>
        @endif

        @forelse($areas as $area)
            @php
                $vigentes = $area->holders->whereNull('ended_at');
                $encerrados = $area->holders->whereNotNull('ended_at');
                $orfaos = $orphans[$area->id] ?? collect();
            @endphp
            <section class="space-y-4 rounded-card bg-surface p-6 shadow-card" data-director-area="{{ $area->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-display text-base font-semibold text-ink">{{ $area->name }}</h3>
                        @if($area->description)
                            <p class="text-xs text-ink-2">{{ $area->description }}</p>
                        @endif
                    </div>
                    <x-pill :kind="$area->active ? 'ok' : 'off'">{{ $area->active ? 'Ativa' : 'Desativada' }}</x-pill>
                </div>

                @if($orfaos->isNotEmpty())
                    <div class="rounded-xl bg-warn-soft p-4 text-sm text-ink" data-director-orphans>
                        <p class="font-bold">Documentos pendentes de quem não responde mais por esta área</p>
                        <p class="mb-2 text-xs text-ink-2">
                            Documento congelado não troca de signatário. Cancele e emita de novo, escolhendo quem assume.
                        </p>
                        <ul class="list-disc space-y-1 pl-5 text-xs">
                            @foreach($orfaos as $signatario)
                                <li>
                                    <a href="{{ route('signature-documents.show', $signatario->signature_document_id) }}"
                                       class="font-semibold text-grena-ink hover:underline">{{ $signatario->document?->title }}</a>
                                    — {{ $signatario->name }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div>
                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-3">Quem assina pela área</h4>
                    @if($vigentes->isEmpty())
                        <p class="text-sm text-ink-2">Ninguém ainda. Sem titular nem substituto, a área não aparece no documento.</p>
                    @else
                        <ul class="divide-y divide-line rounded-xl border border-line">
                            @foreach($vigentes as $mandato)
                                <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3" data-director-holder="{{ $mandato->id }}">
                                    <div>
                                        <span class="font-semibold text-ink">{{ $mandato->user_name }}</span>
                                        <x-pill :kind="$mandato->role === \App\Models\SignatureDirectorAreaHolder::ROLE_PRIMARY ? 'info' : 'off'">{{ $mandato->roleLabel() }}</x-pill>
                                        <div class="text-xs text-ink-2">
                                            Desde {{ $mandato->starts_on?->format('d/m/Y') }} · definido por {{ $mandato->set_by_name ?? 'não registrado' }}
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('signature-directors.end', $mandato) }}"
                                          onsubmit="return confirm(@js('Encerrar o mandato de ' . $mandato->user_name . ' nesta área?'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-bold text-danger hover:underline">Encerrar</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if($area->active && $candidates->isNotEmpty())
                    <form method="POST" action="{{ route('signature-directors.assign', $area) }}"
                          class="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        @csrf
                        <div class="md:col-span-5">
                            <label class="mb-1 block text-xs font-bold text-ink-2">Diretor</label>
                            <select name="user_id" required class="{{ $campoMenor }}">
                                <option value="">Escolha…</option>
                                @foreach($candidates as $candidato)
                                    <option value="{{ $candidato->id }}">{{ $candidato->name }} (matrícula {{ $candidato->matricula }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-3">
                            <label class="mb-1 block text-xs font-bold text-ink-2">Papel</label>
                            <select name="role" required class="{{ $campoMenor }}">
                                @foreach($roles as $valor => $rotulo)
                                    <option value="{{ $valor }}">{{ $rotulo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-bold text-ink-2">Desde</label>
                            <input type="date" name="starts_on" value="{{ now()->toDateString() }}" class="{{ $campoMenor }}">
                        </div>
                        <div class="md:col-span-2">
                            <x-secondary-button type="submit" class="w-full justify-center">Incluir</x-secondary-button>
                        </div>
                        <p class="text-xs text-ink-2 md:col-span-12">
                            Um titular novo encerra o mandato do titular atual. Substitutos podem ser vários.
                        </p>
                    </form>
                @endif

                <details class="text-sm">
                    <summary class="cursor-pointer text-xs font-bold text-grena-ink">Editar a área</summary>
                    <form method="POST" action="{{ route('signature-directors.update', $area) }}" class="mt-3 grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                        @csrf
                        @method('PUT')
                        <div class="md:col-span-4">
                            <label class="mb-1 block text-xs font-bold text-ink-2">Nome</label>
                            <input type="text" name="name" maxlength="120" required value="{{ $area->name }}" class="{{ $campoMenor }}">
                        </div>
                        <div class="md:col-span-5">
                            <label class="mb-1 block text-xs font-bold text-ink-2">Descrição</label>
                            <input type="text" name="description" maxlength="255" value="{{ $area->description }}" class="{{ $campoMenor }}">
                        </div>
                        <label class="flex items-center gap-2 text-xs text-ink-2 md:col-span-1">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" @checked($area->active)> Ativa
                        </label>
                        <div class="md:col-span-2">
                            <x-secondary-button type="submit" class="w-full justify-center">Salvar</x-secondary-button>
                        </div>
                    </form>
                </details>

                @if($encerrados->isNotEmpty())
                    <details class="text-xs text-ink-2">
                        <summary class="cursor-pointer font-bold text-ink-3">Mandatos encerrados ({{ $encerrados->count() }})</summary>
                        <ul class="mt-2 space-y-1">
                            @foreach($encerrados as $mandato)
                                <li>
                                    {{ $mandato->user_name }} — {{ mb_strtolower($mandato->roleLabel()) }},
                                    de {{ $mandato->starts_on?->format('d/m/Y') }} a {{ $mandato->ended_at?->format('d/m/Y') }}
                                    (encerrado por {{ $mandato->ended_by_name ?? 'não registrado' }})
                                </li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </section>
        @empty
            <x-empty-state icon="doc">Nenhuma área cadastrada ainda.</x-empty-state>
        @endforelse
    </x-page>
</x-app-layout>

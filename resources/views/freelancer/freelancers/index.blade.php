{{--
    Cadastro de freelancers em cartões: foto (ou iniciais), alertas de excesso
    de serviços e de cadastro incompleto, e as funções em que já atuou.
    A lista vem inteira: a busca filtra na página.
--}}
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Freelancers">
            Cadastro interno de freelancers.
            <x-slot:actions>
                <x-primary-button-a href="{{ route('freelancers.create') }}"><x-icon name="user-plus" /> Novo freelancer</x-primary-button-a>
            </x-slot:actions>
        </x-page-title>

        @include('partials.alerts')

        @if($freelancers->isEmpty())
            <x-empty-state icon="user">
                Nenhum freelancer encontrado.
                <a href="{{ route('freelancers.create') }}" class="font-bold text-grena-ink hover:underline">Cadastrar freelancer</a>.
            </x-empty-state>
        @else
            <x-search-bar mode="client" target="#freelancers" placeholder="Buscar por nome, CPF, PIX ou função" />

            <div id="freelancers" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @foreach($freelancers as $freelancer)
                    @php
                        $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($freelancer->name), -1, PREG_SPLIT_NO_EMPTY))->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode(''));
                        $search = implode(' ', [$freelancer->name, $freelancer->cpf, preg_replace('/\D/', '', (string) $freelancer->cpf), $freelancer->pix_key, $freelancer->email, implode(' ', array_keys($freelancer->function_counts ?? []))]);
                    @endphp
                    <article data-search="{{ $search }}"
                             class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card {{ $freelancer->exceeds_weekly_limit ? 'ring-2 ring-warn/50' : '' }}">
                        <div class="flex items-start gap-4">
                            <a href="{{ route('freelancers.show', $freelancer) }}" class="relative grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-full font-display text-lg font-semibold"
                               style="{{ \App\View\AreaColor::style('freela') }}" tabindex="-1" aria-hidden="true">
                                {{ $initials }}
                                @if($freelancer->imageUrl())
                                    <img src="{{ $freelancer->imageUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                                @endif
                            </a>

                            <div class="min-w-0 flex-1">
                                <a href="{{ route('freelancers.show', $freelancer) }}" class="font-display text-base font-semibold tracking-tight text-ink hover:text-grena-ink">{{ $freelancer->name }}</a>
                                <dl class="mt-1 grid gap-0.5 text-sm text-ink-2">
                                    <div><dt class="sr-only">CPF</dt><dd class="font-mono text-xs">{{ $freelancer->cpf }}</dd></div>
                                    <div class="truncate"><dt class="inline text-ink-3">PIX </dt><dd class="inline font-mono text-xs">{{ $freelancer->pix_key }}</dd></div>
                                    @if($freelancer->email)
                                        <div class="truncate"><dt class="sr-only">E-mail</dt><dd>{{ $freelancer->email }}</dd></div>
                                    @endif
                                </dl>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <a href="{{ route('freelancers.show', $freelancer) }}" aria-label="Editar {{ $freelancer->name }}"
                                   class="grid h-9 w-9 place-items-center rounded-full text-ink-2 transition hover:bg-subtle hover:text-ink">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form method="POST" action="{{ route('freelancers.destroy', $freelancer) }}"
                                      onsubmit="return confirm('Excluir o freelancer \'{{ $freelancer->name }}\' permanentemente?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" aria-label="Excluir {{ $freelancer->name }}"
                                            class="grid h-9 w-9 place-items-center rounded-full text-ink-3 transition hover:bg-danger-soft hover:text-danger">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </div>

                        @if($freelancer->exceeds_weekly_limit || ! $freelancer->hasCompleteContractData())
                            <div class="flex flex-wrap gap-1.5">
                                @if($freelancer->exceeds_weekly_limit)
                                    <span title="Mais de {{ \App\Models\FreelancerService::WEEKLY_LIMIT }} serviços numa janela de 7 dias">
                                        <x-pill kind="warn">Excesso de serviços</x-pill>
                                    </span>
                                @endif
                                @unless($freelancer->hasCompleteContractData())
                                    <span title="Faltam: {{ implode(', ', $freelancer->missingContractFieldLabels()) }}">
                                        <x-pill kind="danger">Cadastro incompleto</x-pill>
                                    </span>
                                @endunless
                            </div>
                        @endif

                        {{-- Funções em que o freelancer já atuou. Cancelados e aditivos
                             ficam de fora da conta - ver FreelancerService::functionCountsFor(). --}}
                        @if(!empty($freelancer->function_counts))
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($freelancer->function_counts as $functionName => $total)
                                    <span title="{{ $total }} serviço(s) como {{ $functionName }}"
                                          class="rounded-full px-2.5 py-0.5 text-xs font-bold" style="{{ \App\View\AreaColor::style('freela') }}">
                                        {{ $functionName }} · {{ $total }}
                                    </span>
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-auto flex items-center justify-between border-t border-line pt-3 text-xs text-ink-3">
                            <span class="font-mono">#{{ $freelancer->id }}</span>
                            <span>{{ $freelancer->freelancer_services_count }} {{ $freelancer->freelancer_services_count == 1 ? 'serviço' : 'serviços' }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </x-page>
</x-app-layout>

{{-- Inscrições de um torneio: lista com busca e filtros, inscrição pelo
     balcão, baixa manual (marque as inscrições pendentes de uma mesma pessoa)
     e cancelamento caso a caso, com ou sem estorno pela eRede. --}}
@php
    use App\Authorization\Permissions as P;
    use App\Models\Tournament\TournamentRegistration as Registration;

    $money = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    $field = 'h-11 rounded-full border border-line-strong bg-surface px-4 text-sm text-ink focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
    $kind = fn (string $status) => match ($status) {
        Registration::STATUS_PAID => 'ok',
        Registration::STATUS_PENDING_PAYMENT => 'warn',
        Registration::STATUS_CANCELLED => 'danger',
        default => 'off',
    };
    $canManual = auth()->user()->can(P::TORNEIOS_PAGAMENTOS_BAIXA_MANUAL);
    $canRefund = auth()->user()->can(P::TORNEIOS_PAGAMENTOS_ESTORNAR);
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="'Inscrições — ' . $tournament->title" :back="route('tournaments.show', $tournament)">
            Inscrição individual: cada pessoa paga a sua parte, pela eRede no site ou por baixa manual. Desistência e estorno de inscrição paga são decididos aqui, caso a caso.
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <x-search-bar :filters="['category', 'status']" placeholder="Nome, CPF ou matrícula">
            <x-slot:controls>
                <label for="filtro-categoria" class="sr-only">Categoria</label>
                <select id="filtro-categoria" name="category" class="{{ $field }} w-full sm:w-56">
                    <option value="">Todas as categorias</option>
                    @foreach ($tournament->categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <label for="filtro-situacao" class="sr-only">Situação</label>
                <select id="filtro-situacao" name="status" class="{{ $field }} w-full sm:w-56">
                    <option value="">Todas as situações</option>
                    @foreach (Registration::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-slot:controls>
        </x-search-bar>

        @if ($registrations->isEmpty())
            <x-empty-state icon="users">Nenhuma inscrição encontrada.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                @if ($canManual)<th class="px-3 py-3.5"><span class="sr-only">Marcar para baixa</span></th>@endif
                                <th class="{{ $th }}">Pessoa</th>
                                <th class="{{ $th }}">Categoria</th>
                                <th class="{{ $th }}">Valor</th>
                                <th class="{{ $th }} text-center">Situação</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($registrations as $registration)
                                <tr>
                                    @if ($canManual)
                                        <td class="px-3 py-3.5 text-center">
                                            @if ($registration->status === Registration::STATUS_PENDING_PAYMENT)
                                                <input type="checkbox" form="baixa-manual" name="registration_ids[]" value="{{ $registration->id }}" aria-label="Baixa manual de {{ $registration->name }}" class="h-4 w-4 rounded border-line-strong text-grena-ink">
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-5 py-3.5">
                                        <p class="font-semibold text-ink">{{ $registration->name }}</p>
                                        <p class="font-mono text-xs text-ink-3">{{ \App\Support\Cpf::format($registration->cpf) }} · matrícula {{ $registration->title }}</p>
                                        @if ($registration->eligibility_override_reason)
                                            <p class="text-xs text-warn">Liberada: {{ $registration->eligibility_override_reason }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-ink-2">{{ $registration->category?->name }}</td>
                                    <td class="px-5 py-3.5 font-mono">
                                        {{ $money($registration->amount) }}
                                        @if ($registration->payment)
                                            <span class="block text-xs text-ink-3">{{ $registration->payment->is_manual ? 'baixa manual' : 'eRede' }}@if ((float) $registration->payment->refunded_amount > 0) · estornado {{ $money($registration->payment->refunded_amount) }}@endif</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <x-pill :kind="$kind($registration->status)">{{ Registration::STATUS_LABELS[$registration->status] ?? $registration->status }}</x-pill>
                                        @if ($registration->cancel_reason)<span class="block text-xs text-ink-3">{{ $registration->cancel_reason }}</span>@endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        @if ($registration->isActive())
                                            <details class="text-left">
                                                <summary class="cursor-pointer text-sm font-semibold text-danger">Cancelar</summary>
                                                <form method="POST" action="{{ route('tournaments.registrations.cancel', $registration) }}" class="mt-2 flex w-64 flex-col gap-2">
                                                    @csrf
                                                    <x-text-input name="cancel_reason" required maxlength="500" placeholder="Motivo" />
                                                    @if ($canRefund && $registration->status === Registration::STATUS_PAID && $registration->payment && ! $registration->payment->is_manual)
                                                        <label class="flex items-center gap-2 text-xs text-ink-2">
                                                            <input type="checkbox" name="refund" value="1" class="h-4 w-4 rounded border-line-strong text-grena-ink">
                                                            Estornar {{ $money($registration->amount) }} pela eRede
                                                        </label>
                                                    @endif
                                                    <x-danger-button size="sm">Confirmar cancelamento</x-danger-button>
                                                </form>
                                            </details>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $registrations->links() }}

            @if ($canManual)
                <form id="baixa-manual" method="POST" action="{{ route('tournaments.registrations.manual', $tournament) }}" class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-card">
                    @csrf
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-3">Baixa manual</h2>
                    <p class="text-sm text-ink-2">Marque na lista as inscrições pendentes de uma mesma pessoa que pagou fora da eRede. O valor é o total delas.</p>
                    @include('payments.partials.manual-fields')
                    <div class="text-right"><x-primary-button>Registrar pagamento</x-primary-button></div>
                </form>
            @endif
        @endif

        <form method="POST" action="{{ route('tournaments.registrations.store', $tournament) }}" class="grid grid-cols-1 gap-4 rounded-card bg-surface p-5 shadow-card sm:grid-cols-4 sm:items-end">
            @csrf
            <h2 class="text-sm font-black uppercase tracking-widest text-ink-3 sm:col-span-4">Inscrever pelo balcão</h2>
            <p class="text-sm text-ink-2 sm:col-span-4">A pessoa precisa ter cadastro no site de locação. Os dados (sexo, nascimento, e-mail) vêm do MultiClubes. As inscrições só abrem dentro do período do torneio.</p>
            <div><x-input-label for="cpf" value="CPF" /><x-text-input id="cpf" name="cpf" class="mt-1 font-mono" :value="old('cpf')" required maxlength="20" /></div>
            <div><x-input-label for="title" value="Matrícula" /><x-text-input id="title" name="title" class="mt-1 font-mono" :value="old('title')" required maxlength="20" /></div>
            <fieldset class="sm:col-span-2">
                <legend class="text-sm font-semibold text-ink">Categorias</legend>
                <div class="mt-1 flex flex-wrap gap-3">
                    @foreach ($tournament->categories as $category)
                        <label class="flex items-center gap-2 text-sm text-ink-2">
                            <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', []))) class="h-4 w-4 rounded border-line-strong text-grena-ink">
                            {{ $category->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="sm:col-span-3">
                <x-input-label for="override_reason" value="Justificativa para liberar (só se o sexo ou a idade não se encaixam)" />
                <x-text-input id="override_reason" name="override_reason" class="mt-1" :value="old('override_reason')" maxlength="500" placeholder="Ex.: cadastro do MultiClubes com sexo errado, confirmado com documento" />
            </div>
            <div class="sm:text-right"><x-primary-button>Inscrever</x-primary-button></div>
        </form>
    </x-page>
</x-app-layout>

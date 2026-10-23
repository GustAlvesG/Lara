{{-- Uma conta do site: diagnóstico (outras contas do CPF, MultiClubes,
     diferenças), corrigir e-mail/telefone, trazer do MultiClubes, senha
     provisória, desativar duplicada, histórico. --}}
@php
    use App\Models\MemberAccountLog as Log;
    use App\Support\Cpf;

    $profile = $diagnosis['profile'];
    $labels = ['name' => 'Nome', 'email' => 'E-mail', 'telephone' => 'Telefone'];
    $isLogin = $diagnosis['login_account_id'] === $member->id;
    $others = $diagnosis['siblings']->where('id', '!=', $member->id);
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title :title="$member->name ?: 'Conta sem nome'" :back="route('site-accounts.index')">
            CPF {{ Cpf::format($member->cpf) }} · matrícula {{ $member->title }}
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if (session('temporary_password'))
            <section class="rounded-card bg-ok-soft p-5 text-ok">
                <p class="text-sm">Senha provisória (anote agora — ela não aparece de novo):</p>
                <p class="mt-1 font-mono text-2xl font-bold tracking-widest">{{ session('temporary_password') }}</p>
                <p class="mt-2 text-sm">A pessoa entra no site com o CPF {{ Cpf::format($member->cpf) }} e esta senha, e troca por uma dela em "Alterar senha".</p>
            </section>
        @endif

        {{-- Diagnóstico: o que costuma explicar "meu login não entra". --}}
        <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Diagnóstico</h2>
            <ul class="flex flex-col gap-2 text-sm">
                @if ($member->trashed())
                    <li class="text-danger">Conta <strong>desativada</strong> em {{ $member->deleted_at->format('d/m/Y H:i') }}: não entra no site.</li>
                @elseif ($others->whereNull('deleted_at')->isNotEmpty())
                    <li class="text-warn">
                        O CPF tem <strong>{{ $diagnosis['siblings']->whereNull('deleted_at')->count() }} contas ativas</strong>. O login entra só na mais antiga
                        ({{ $isLogin ? 'esta' : 'a nº ' . $diagnosis['login_account_id'] }}): se a senha que a pessoa usa é a da outra, ela não entra. Desative a conta errada.
                    </li>
                @else
                    <li class="text-ok">Única conta ativa deste CPF.</li>
                @endif

                @if ($diagnosis['mc_error'])
                    <li class="text-warn">{{ $diagnosis['mc_error'] }}</li>
                @elseif (! $profile)
                    <li class="text-danger">Sem sócio de <strong>título ativo</strong> no MultiClubes para este CPF: o site pode recusar reservas. Confira a situação do título no MultiClubes.</li>
                @elseif (! $diagnosis['title_matches'])
                    <li class="text-warn">No MultiClubes o CPF está na matrícula <strong>{{ $profile->title }}</strong>, e a conta diz {{ $member->title }}.</li>
                @else
                    <li class="text-ok">Sócio ativo no MultiClubes (matrícula {{ $profile->title }}).</li>
                @endif

                @if (! $member->email)
                    <li class="text-warn">Conta <strong>sem e-mail</strong>: avisos de reserva e torneio não chegam.</li>
                @endif
                @if ($diagnosis['legacy_password'])
                    <li class="text-ink-3">Senha ainda no formato antigo (é convertida no próximo login certo). Se a pessoa não lembra, gere uma provisória.</li>
                @endif
            </ul>

            @if ($diagnosis['differences'])
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-ink-3"><tr><th class="py-1 pr-3">Campo</th><th class="py-1 pr-3">No site</th><th class="py-1">No MultiClubes</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($diagnosis['differences'] as $field => $pair)
                                <tr><td class="py-1.5 pr-3 text-ink-3">{{ $labels[$field] }}</td><td class="py-1.5 pr-3 text-ink">{{ $pair['site'] ?: '—' }}</td><td class="py-1.5 text-ink">{{ $pair['mc'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">E-mail e telefone</h2>
                <form method="POST" action="{{ route('site-accounts.update', $member->id) }}" class="flex flex-col gap-3">
                    @csrf @method('PUT')
                    <div><x-input-label for="email" value="E-mail" /><x-text-input id="email" type="email" name="email" class="mt-1" :value="old('email', $member->email)" maxlength="255" /></div>
                    <div><x-input-label for="telephone" value="Telefone" /><x-text-input id="telephone" name="telephone" class="mt-1" :value="old('telephone', $member->telephone)" maxlength="30" /></div>
                    <div><x-secondary-button type="submit">Salvar</x-secondary-button></div>
                </form>
                @if ($profile)
                    <form method="POST" action="{{ route('site-accounts.pull', $member->id) }}" onsubmit="return confirm('Copiar nome, e-mail e telefone do MultiClubes para esta conta?')">
                        @csrf
                        <x-secondary-button type="submit" size="sm">Trazer do MultiClubes</x-secondary-button>
                    </form>
                @endif
            </section>

            <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Senha</h2>
                <p class="text-sm text-ink-2">Gera uma senha provisória e mostra aqui uma vez. Passe para a pessoa (balcão ou telefone, depois de confirmar quem é); ela entra com o CPF e troca no site. A senha atual deixa de valer.</p>
                @unless ($member->trashed())
                    <form method="POST" action="{{ route('site-accounts.password', $member->id) }}" onsubmit="return confirm('Gerar senha provisória? A senha atual da pessoa deixa de valer.')">
                        @csrf
                        <x-primary-button>Gerar senha provisória</x-primary-button>
                    </form>
                @endunless
            </section>
        </div>

        @if ($diagnosis['siblings']->count() > 1)
            <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card">
                <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Contas com este CPF</h2>
                <ul class="divide-y divide-line text-sm">
                    @foreach ($diagnosis['siblings'] as $sibling)
                        @php $linked = $diagnosis['linked'][$sibling->id] ?? []; @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div>
                                <p class="text-ink">
                                    <a href="{{ route('site-accounts.show', $sibling->id) }}" class="font-semibold">Conta nº {{ $sibling->id }}</a>
                                    @if ($sibling->id === $member->id)<span class="text-ink-3">(esta)</span>@endif
                                    @if ($sibling->id === $diagnosis['login_account_id'])<x-pill kind="info">o login usa esta</x-pill>@endif
                                    @if ($sibling->trashed())<x-pill kind="off">desativada</x-pill>@endif
                                </p>
                                <p class="text-xs text-ink-3">
                                    Criada em {{ $sibling->created_at?->format('d/m/Y H:i') ?? '—' }} · {{ $sibling->email ?: 'sem e-mail' }} ·
                                    {{ $linked ? collect($linked)->map(fn ($n, $label) => "{$n} {$label}")->implode(', ') : 'nada ligado' }}
                                </p>
                            </div>
                            @if ($sibling->id === $member->id)
                                @if ($member->trashed())
                                    <form method="POST" action="{{ route('site-accounts.restore', $member->id) }}">
                                        @csrf
                                        <x-secondary-button type="submit" size="sm">Reativar</x-secondary-button>
                                    </form>
                                @elseif ($others->whereNull('deleted_at')->isNotEmpty())
                                    <form method="POST" action="{{ route('site-accounts.destroy', $member->id) }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Desativar esta conta? Ela sai do login; reservas e inscrições continuam guardadas.')">
                                        @csrf @method('DELETE')
                                        <x-text-input name="reason" class="h-8 text-sm" placeholder="Motivo" required maxlength="255" aria-label="Motivo" />
                                        <x-danger-button size="sm">Desativar esta</x-danger-button>
                                    </form>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @elseif ($member->trashed())
            <form method="POST" action="{{ route('site-accounts.restore', $member->id) }}">
                @csrf
                <x-secondary-button type="submit">Reativar a conta</x-secondary-button>
            </form>
        @endif

        <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-card">
            <h2 class="font-display text-lg font-semibold tracking-tight text-ink">Histórico</h2>
            @if ($logs->isEmpty())
                <p class="text-sm text-ink-3">Nada feito pela equipe nesta conta ainda.</p>
            @else
                <ul class="flex flex-col gap-1 text-sm text-ink-2">
                    @foreach ($logs as $log)
                        <li>
                            <span class="font-mono text-xs text-ink-3">{{ $log->created_at?->format('d/m/Y H:i') }}</span>
                            {{ $log->user?->name ?? '—' }} {{ Log::ACTION_LABELS[$log->action] ?? $log->action }}
                            @if (data_get($log->details, 'reason')) <span class="text-ink-3">— {{ data_get($log->details, 'reason') }}</span>@endif
                            @if (data_get($log->details, 'after.email') !== null && data_get($log->details, 'after.email') !== data_get($log->details, 'before.email'))
                                <span class="text-ink-3">(e-mail: {{ data_get($log->details, 'before.email') ?: '—' }} → {{ data_get($log->details, 'after.email') }})</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </x-page>
</x-app-layout>

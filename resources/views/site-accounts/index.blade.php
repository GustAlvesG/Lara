{{-- Reservas › Contas do site: as contas dos sócios no site de locação,
     com busca, filtro das duplicadas e cadastro manual. --}}
@php
    use App\Support\Cpf;

    $th = 'px-5 py-3.5 text-left text-[11px] font-bold uppercase tracking-[0.08em] text-ink-3';
@endphp
<x-app-layout :bootstrap-grid="false">
    <x-page>
        <x-page-title title="Contas do site">
            Contas dos sócios no site de locação (login = CPF). Resolva aqui "esqueci a senha", "meu login não entra" e "meu e-mail está errado".
        </x-page-title>

        @include('partials.alerts')
        @if ($errors->any())
            <div class="rounded-2xl bg-danger-soft px-5 py-4 text-sm text-danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if ($duplicated->isNotEmpty())
            <p class="rounded-2xl bg-warn-soft px-5 py-3 text-sm text-warn">
                {{ $duplicated->count() }} CPF(s) com mais de uma conta ativa — o login entra só na mais antiga.
                <a href="{{ route('site-accounts.index', ['duplicadas' => 1]) }}" class="font-semibold underline">Ver as duplicadas</a>
            </p>
        @endif

        <x-search-bar :filters="['duplicadas']" placeholder="CPF, matrícula, nome ou e-mail">
            <x-slot:controls>
                <label class="flex items-center gap-2 text-sm text-ink-2">
                    <input type="checkbox" name="duplicadas" value="1" @checked($onlyDuplicated) class="h-4 w-4 rounded border-line-strong text-grena-ink">
                    Só duplicadas
                </label>
            </x-slot:controls>
        </x-search-bar>

        <details class="rounded-card bg-surface p-5 shadow-card" @if ($errors->has('cpf') || $errors->has('title')) open @endif>
            <summary class="cursor-pointer font-semibold text-ink">Cadastrar conta para um sócio</summary>
            <form method="POST" action="{{ route('site-accounts.store') }}" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                @csrf
                <div><x-input-label for="new-cpf" value="CPF" /><x-text-input id="new-cpf" name="cpf" class="mt-1" :value="old('cpf')" required inputmode="numeric" /></div>
                <div><x-input-label for="new-title" value="Matrícula (título)" /><x-text-input id="new-title" name="title" class="mt-1" :value="old('title')" required /></div>
                <div><x-primary-button>Cadastrar</x-primary-button></div>
                <p class="text-xs text-ink-3 sm:col-span-3">Precisa ser sócio de título ativo no MultiClubes; nome, nascimento, e-mail e telefone vêm de lá. O Lara gera uma senha provisória para você passar à pessoa.</p>
            </form>
        </details>

        @if ($members->isEmpty())
            <x-empty-state icon="users">Nenhuma conta encontrada.</x-empty-state>
        @else
            <div class="overflow-hidden rounded-card bg-surface shadow-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-line bg-subtle">
                                <th class="{{ $th }}">Nome</th>
                                <th class="{{ $th }}">CPF</th>
                                <th class="{{ $th }}">Matrícula</th>
                                <th class="{{ $th }}">E-mail</th>
                                <th class="{{ $th }}"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($members as $member)
                                <tr>
                                    <td class="px-5 py-3.5"><a href="{{ route('site-accounts.show', $member->id) }}" class="font-semibold text-ink no-underline hover:underline">{{ $member->name ?: '(sem nome)' }}</a></td>
                                    <td class="px-5 py-3.5 font-mono text-ink-2">{{ Cpf::format($member->cpf) }}</td>
                                    <td class="px-5 py-3.5 font-mono text-ink-2">{{ $member->title }}</td>
                                    <td class="px-5 py-3.5 text-ink-2">{{ $member->email ?: '—' }}</td>
                                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                                        @if ($member->trashed())<x-pill kind="off">desativada</x-pill>@endif
                                        @if (! $member->trashed() && isset($duplicated[$member->cpf]))<x-pill kind="warn">CPF duplicado</x-pill>@endif
                                        @if (! $member->email)<x-pill kind="warn">sem e-mail</x-pill>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            {{ $members->links() }}
        @endif
    </x-page>
</x-app-layout>

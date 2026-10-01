@php
    $label = 'mb-1.5 block text-sm font-bold text-ink';
    $input = 'h-12 w-full rounded-xl border border-line-strong bg-surface px-4 text-base text-ink placeholder:text-ink-3 transition focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-guest-layout>
    <x-auth-card title="Crie sua conta" lead="Preencha os dados abaixo para se registrar.">
        <form method="POST" action="/register">
            @csrf

            <div class="mb-4">
                <label for="name" class="{{ $label }}">Nome completo</label>
                <input type="text" id="name" name="name" required autocomplete="name" autofocus value="{{ old('name') }}"
                       placeholder="Seu nome" class="{{ $input }}">
            </div>

            <div class="mb-4">
                <label for="email" class="{{ $label }}">E-mail</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="{{ old('email') }}"
                       placeholder="seu.email@exemplo.com" class="{{ $input }}">
            </div>

            <div class="mb-4">
                <label for="matricula" class="{{ $label }}">Matrícula</label>
                <input type="text" id="matricula" name="matricula" maxlength="5" autocomplete="off" value="{{ old('matricula') }}"
                       placeholder="Ex: 00123" class="{{ $input }} font-mono">
            </div>

            <div class="mb-4">
                <label for="password" class="{{ $label }}">Senha</label>
                <input type="password" id="password" name="password" required autocomplete="new-password"
                       placeholder="••••••••" class="{{ $input }}">
            </div>

            <div class="mb-5">
                <label for="password_confirmation" class="{{ $label }}">Confirmar senha</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                       placeholder="••••••••" class="{{ $input }}">
            </div>

            {{-- Antes só apareciam erros de e-mail e senha; nome e matrícula falhavam em silêncio. --}}
            @if ($errors->any())
                <div id="login-error-message" class="mb-5 rounded-2xl bg-danger-soft p-3 text-sm font-medium text-danger" role="alert">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <x-primary-button class="h-12 w-full text-base">Registrar</x-primary-button>

            <p class="mt-5 text-center text-sm text-ink-2">
                Já tem uma conta?
                <a href="/login" class="font-bold text-grena-ink hover:underline">Faça login</a>
            </p>
        </form>
    </x-auth-card>
</x-guest-layout>

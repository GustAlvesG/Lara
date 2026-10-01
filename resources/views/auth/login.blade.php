@php
    // Aba escolhida: o que voltou do formulário com erro, senão matrícula —
    // é como a maioria entra; o e-mail fica como segunda opção.
    $loginType = old('login_type') === 'email' ? 'email' : 'matricula';
    $label = 'mb-1.5 block text-sm font-bold text-ink';
    $input = 'h-12 w-full rounded-xl border border-line-strong bg-surface px-4 text-base text-ink placeholder:text-ink-3 transition focus:border-grena focus:ring-4 focus:ring-grena-tint';
@endphp
<x-guest-layout>
    <x-auth-card title="LARA" lead="Entre com suas credenciais para continuar.">
        <form method="POST" action="/login" id="login-form" data-login-type="{{ $loginType }}">
            @csrf

            <input type="hidden" name="login_type" id="login_type" value="{{ $loginType }}">

            {{-- Alternador: Matrícula x E-mail --}}
            <div class="mb-5 grid grid-cols-2 gap-1 rounded-full bg-subtle p-1" role="tablist">
                <button type="button" data-login-tab="matricula" role="tab"
                    class="login-tab rounded-full px-4 py-2 text-sm font-bold transition">
                    Matrícula
                </button>
                <button type="button" data-login-tab="email" role="tab"
                    class="login-tab rounded-full px-4 py-2 text-sm font-bold transition">
                    E-mail
                </button>
            </div>

            <div class="mb-4" data-login-field="email" @if($loginType !== 'email') hidden @endif>
                <label for="email" class="{{ $label }}">E-mail</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="{{ old('email') }}"
                    @disabled($loginType !== 'email')
                    placeholder="seu.email@exemplo.com"
                    class="{{ $input }}">
            </div>

            <div class="mb-4" data-login-field="matricula" @if($loginType !== 'matricula') hidden @endif>
                <label for="matricula" class="{{ $label }}">Matrícula</label>
                <input type="text" id="matricula" name="matricula" required autocomplete="username"
                    maxlength="5" inputmode="numeric" value="{{ old('matricula') }}"
                    @disabled($loginType !== 'matricula')
                    placeholder="12345"
                    class="{{ $input }} font-mono">
            </div>

            <div class="mb-5">
                <label for="password" class="{{ $label }}">Senha</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                    placeholder="••••••••"
                    class="{{ $input }}">
            </div>

            @if ($errors->has('email') || $errors->has('matricula') || $errors->has('password'))
                <div id="login-error-message" class="mb-5 flex items-start gap-2 rounded-2xl bg-danger-soft p-3 text-sm font-medium text-danger" role="alert">
                    <x-icon name="x" class="mt-0.5 h-4 w-4" />
                    <span>{{ $errors->first($loginType) ?: $errors->first('password') }}</span>
                </div>
            @endif

            <x-primary-button class="h-12 w-full text-base">Entrar</x-primary-button>

            <p class="mt-5 text-center text-sm text-ink-2">
                Ainda não tem uma conta?
                <a href="/register" class="font-bold text-grena-ink hover:underline">Registre-se</a>
            </p>
        </form>
    </x-auth-card>

    <script>
        (function () {
            const form = document.getElementById('login-form');
            if (!form) return;

            const hidden = document.getElementById('login_type');
            const tabs = form.querySelectorAll('[data-login-tab]');
            const fields = form.querySelectorAll('[data-login-field]');

            const ACTIVE = ['bg-surface', 'shadow-card', 'text-ink'];
            const IDLE = ['text-ink-2'];

            function select(type) {
                hidden.value = type;

                tabs.forEach(tab => {
                    const on = tab.dataset.loginTab === type;
                    tab.setAttribute('aria-selected', on ? 'true' : 'false');
                    tab.classList.toggle('cursor-default', on);
                    ACTIVE.forEach(c => tab.classList.toggle(c, on));
                    IDLE.forEach(c => tab.classList.toggle(c, !on));
                });

                fields.forEach(field => {
                    const on = field.dataset.loginField === type;
                    field.hidden = !on;
                    // Desabilitado não é enviado no POST nem trava o "required" do navegador.
                    field.querySelectorAll('input').forEach(input => { input.disabled = !on; });
                });

                const active = form.querySelector('[data-login-field="' + type + '"] input');
                if (active) active.focus();
            }

            tabs.forEach(tab => tab.addEventListener('click', () => select(tab.dataset.loginTab)));

            select(form.dataset.loginType);
        })();
    </script>
</x-guest-layout>

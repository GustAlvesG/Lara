@php
    // Aba escolhida: o que voltou do formulário com erro, senão matrícula —
    // é como a maioria entra; o e-mail fica como segunda opção.
    $loginType = old('login_type') === 'email' ? 'email' : 'matricula';
    $label = 'mb-1.5 block text-sm font-bold text-ink';
    $input = 'h-12 w-full rounded-xl border border-line-strong bg-surface px-4 text-base text-ink placeholder:text-ink-3 transition focus:border-grena focus:ring-4 focus:ring-grena-tint';
    $hasError = $errors->has('email') || $errors->has('matricula') || $errors->has('password');
@endphp
<x-auth-layout
    title="Entrar"
    photo="images/auth/sede-social.jpg"
    photo-alt="Área coberta da sede social do Clube dos Funcionários à noite, com mesas de madeira e sócios conversando"
    photo-position="55% 50%"
    tagline="O sistema do Clube, feito pelo Clube."
>
    <x-auth-card variant="flush" eyebrow="Acesso dos funcionários" title="Entrar no Lara"
                 lead="Use sua matrícula ou o e-mail da sua conta.">
        <form method="POST" action="/login" id="login-form" data-login-type="{{ $loginType }}">
            @csrf

            <input type="hidden" name="login_type" id="login_type" value="{{ $loginType }}">

            {{-- Alternador: Matrícula x E-mail --}}
            <div class="mb-6 grid grid-cols-2 gap-1 rounded-full bg-subtle p-1" role="tablist" aria-label="Como você quer entrar">
                <button type="button" data-login-tab="matricula" role="tab" aria-controls="campo-matricula"
                    class="login-tab h-10 rounded-full px-4 text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                    Matrícula
                </button>
                <button type="button" data-login-tab="email" role="tab" aria-controls="campo-email"
                    class="login-tab h-10 rounded-full px-4 text-sm font-bold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-grena-tint">
                    E-mail
                </button>
            </div>

            <div class="mb-5" id="campo-email" data-login-field="email" @if($loginType !== 'email') hidden @endif>
                <label for="email" class="{{ $label }}">E-mail</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="{{ old('email') }}"
                    @disabled($loginType !== 'email')
                    placeholder="seu.email@exemplo.com"
                    @if ($hasError) aria-describedby="login-error-message" @endif
                    class="{{ $input }}">
            </div>

            <div class="mb-5" id="campo-matricula" data-login-field="matricula" @if($loginType !== 'matricula') hidden @endif>
                <label for="matricula" class="{{ $label }}">Matrícula</label>
                <input type="text" id="matricula" name="matricula" required autocomplete="username"
                    maxlength="5" inputmode="numeric" value="{{ old('matricula') }}"
                    @disabled($loginType !== 'matricula')
                    placeholder="12345"
                    @if ($hasError) aria-describedby="login-error-message" @endif
                    class="{{ $input }} font-mono tracking-wider">
            </div>

            <div class="mb-6">
                <label for="password" class="{{ $label }}">Senha</label>
                <x-password-input id="password" autocomplete="current-password" placeholder="Sua senha"
                    :describedby="$hasError ? 'login-error-message' : null" />
            </div>

            @if ($hasError)
                <div id="login-error-message" class="mb-6 flex items-start gap-2.5 rounded-2xl bg-danger-soft p-3.5 text-sm font-medium text-danger" role="alert">
                    <x-icon name="x" class="mt-0.5 h-4 w-4" />
                    <span>{{ $errors->first($loginType) ?: $errors->first('password') }}</span>
                </div>
            @endif

            <x-primary-button class="auth-submit h-12 w-full text-base">
                Entrar <x-icon name="arrow-right" class="h-5 w-5" />
            </x-primary-button>

            <div class="mt-8 space-y-2 border-t border-line pt-6 text-sm text-ink-2">
                <p>
                    Ainda não tem acesso?
                    <a href="{{ route('register') }}" class="auth-link font-bold text-grena-ink">Registre-se</a>
                </p>
                <p>Esqueceu a senha? Peça uma nova à TI.</p>
            </div>
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
            const IDLE = ['text-ink-2', 'hover:text-ink'];

            function select(type, focus) {
                hidden.value = type;

                tabs.forEach(tab => {
                    const on = tab.dataset.loginTab === type;
                    tab.setAttribute('aria-selected', on ? 'true' : 'false');
                    tab.tabIndex = on ? 0 : -1;
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
                if (active && focus) active.focus();
            }

            tabs.forEach(tab => tab.addEventListener('click', () => select(tab.dataset.loginTab, true)));

            // Setas trocam de aba, como em qualquer grupo de abas.
            form.querySelector('[role="tablist"]').addEventListener('keydown', event => {
                if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
                const next = hidden.value === 'matricula' ? 'email' : 'matricula';
                select(next, false);
                form.querySelector('[data-login-tab="' + next + '"]').focus();
                event.preventDefault();
            });

            // Com erro, o foco vai para a senha (o identificador já está preenchido).
            const hasError = !!document.getElementById('login-error-message');
            select(form.dataset.loginType, !hasError);
            if (hasError) document.getElementById('password').focus();
        })();
    </script>
</x-auth-layout>

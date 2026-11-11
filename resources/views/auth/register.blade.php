@php
    $label = 'mb-1.5 block text-sm font-bold text-ink';
    $hint = 'mt-1.5 text-sm text-ink-3';
    $error = 'mt-1.5 text-sm font-medium text-danger';

    // Borda vermelha só no campo que voltou com erro.
    $input = fn (string $field) => 'h-12 w-full rounded-xl border bg-surface px-4 text-base text-ink placeholder:text-ink-3 transition focus:border-grena focus:ring-4 focus:ring-grena-tint '
        . ($errors->has($field) ? 'border-danger' : 'border-line-strong');

    // aria do campo: com erro, aria-invalid + o erro (que toma o lugar da
    // dica); sem erro, só a dica, se houver. Os ids são fixos desta tela.
    $aria = fn (string $field, ?string $hintId = null) => $errors->has($field)
        ? "aria-invalid=\"true\" aria-describedby=\"{$field}-error\""
        : ($hintId ? "aria-describedby=\"{$hintId}\"" : '');
@endphp
<x-auth-layout
    title="Criar conta"
    tagline="Da portaria às reservas, cada módulo no seu lugar."
>
    <x-auth-card variant="flush" eyebrow="Acesso dos funcionários" title="Crie sua conta"
                 lead="A conta nasce sem módulos: depois de criá-la, peça ao coordenador do seu setor que coloque você no setor.">
        <form method="POST" action="/register" id="register-form">
            @csrf

            <div class="grid grid-cols-1 gap-x-4 gap-y-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="{{ $label }}">Nome completo</label>
                    <input type="text" id="name" name="name" required autocomplete="name" value="{{ old('name') }}"
                           placeholder="Seu nome" class="{{ $input('name') }}"
                           {!! $aria('name') !!}>
                    @error('name') <p id="name-error" class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="email" class="{{ $label }}">E-mail</label>
                    <input type="email" id="email" name="email" required autocomplete="email" value="{{ old('email') }}"
                           placeholder="seu.email@exemplo.com" class="{{ $input('email') }}"
                           {!! $aria('email') !!}>
                    @error('email') <p id="email-error" class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="cpf" class="{{ $label }}">CPF</label>
                    <input type="text" id="cpf" name="cpf" required inputmode="numeric" maxlength="14" autocomplete="off" value="{{ old('cpf') }}"
                           placeholder="000.000.000-00" data-mask="cpf" class="{{ $input('cpf') }} font-mono"
                           {!! $aria('cpf', 'cpf-hint') !!}>
                    @error('cpf')
                        <p id="cpf-error" class="{{ $error }}">{{ $message }}</p>
                    @else
                        <p id="cpf-hint" class="{{ $hint }}">Vai impresso nos documentos que você assinar.</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="{{ $label }}">Telefone</label>
                    <input type="tel" id="phone" name="phone" required maxlength="20" autocomplete="tel" value="{{ old('phone') }}"
                           placeholder="(00) 00000-0000" data-mask="phone" class="{{ $input('phone') }}"
                           {!! $aria('phone') !!}>
                    @error('phone') <p id="phone-error" class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="matricula" class="{{ $label }}">Matrícula <span class="font-medium text-ink-3">(opcional)</span></label>
                    <input type="text" id="matricula" name="matricula" maxlength="5" inputmode="numeric" autocomplete="off" value="{{ old('matricula') }}"
                           placeholder="00123" class="{{ $input('matricula') }} font-mono tracking-wider"
                           {!! $aria('matricula', 'matricula-hint') !!}>
                    @error('matricula')
                        <p id="matricula-error" class="{{ $error }}">{{ $message }}</p>
                    @else
                        <p id="matricula-hint" class="{{ $hint }}">Com ela você pode entrar pela matrícula, além do e-mail.</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="{{ $label }}">Senha</label>
                    <x-password-input id="password" autocomplete="new-password" placeholder="Pelo menos 8 caracteres"
                        :invalid="$errors->has('password')" :describedby="$errors->has('password') ? 'password-error' : null" />
                    @error('password') <p id="password-error" class="{{ $error }}">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="{{ $label }}">Confirmar senha</label>
                    <x-password-input id="password_confirmation" autocomplete="new-password" placeholder="Repita a senha" />
                </div>
            </div>

            <x-primary-button class="auth-submit mt-7 h-12 w-full text-base">
                Criar conta <x-icon name="arrow-right" class="h-5 w-5" />
            </x-primary-button>

            <div class="mt-8 border-t border-line pt-6 text-sm text-ink-2">
                <p>
                    Já tem uma conta?
                    <a href="{{ route('login') }}" class="auth-link font-bold text-grena-ink">Entrar no Lara</a>
                </p>
            </div>
        </form>
    </x-auth-card>

    <script>
        (function () {
            const form = document.getElementById('register-form');
            if (!form) return;

            // Máscaras só de exibição: o servidor tira tudo o que não é dígito
            // (UserContactFields::normalize) antes de validar.
            const masks = {
                cpf: d => d.slice(0, 11)
                    .replace(/^(\d{3})(\d)/, '$1.$2')
                    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2'),
                phone: d => {
                    // Com DDI (+55…) fica só em dígitos: o servidor aceita assim.
                    if (d.length > 11) return d;
                    if (d.length <= 2) return d.length ? '(' + d : '';
                    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
                    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
                    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
                },
            };

            form.querySelectorAll('[data-mask]').forEach(input => {
                const mask = masks[input.dataset.mask];
                const apply = () => { input.value = mask(input.value.replace(/\D+/g, '')); };
                input.addEventListener('input', apply);
                if (input.value) apply();
            });

            // Voltou com erro: o foco vai para o primeiro campo errado; senão, para o nome.
            const first = form.querySelector('[aria-invalid="true"]') || document.getElementById('name');
            if (first) first.focus();
        })();
    </script>
</x-auth-layout>

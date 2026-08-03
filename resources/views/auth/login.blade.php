<x-guest-layout>
    <x-slot name="slot">
        <!-- Card de Login Centralizado e Elegante -->
        <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-2xl overflow-hidden transform transition duration-500 hover:shadow-3xl">

            <!-- Cabeçalho do Card -->
            <div class="p-8 backdrop-blur-sm text-white text-center" style="background: #A00001;
                background: linear-gradient(90deg,rgba(160, 0, 1, 1) 0%, rgba(126, 20, 23, 1) 100%);">

                 <!-- ESPAÇO PARA LOGO -->
                <div class="mb-4 flex justify-center">
                    <x-application-logo style="background: white;" class="h-20 w-20 rounded-full border-4 border-white shadow-lg" />
                </div>

                <h1 class="text-3xl font-extrabold mb-1">Lara</h1>
                <p class="text-sm">Entre com suas credenciais para continuar.</p>
            </div>




            <!-- Formulário -->
            <div class="p-8 dark:bg-gray-800">
                @php
                    // Aba escolhida: o que voltou do formulário com erro, senão e-mail.
                    $loginType = old('login_type') === 'matricula' ? 'matricula' : 'email';
                @endphp

                <form method="POST" action="/login" id="login-form" data-login-type="{{ $loginType }}">
                    <!-- TOKEN CSRF (Mantenha para Laravel) -->
                    @csrf

                    <input type="hidden" name="login_type" id="login_type" value="{{ $loginType }}">

                    <!-- Alternador: E-mail x Matrícula -->
                    <div class="mb-5 grid grid-cols-2 gap-1 p-1 bg-gray-100 dark:bg-gray-700 rounded-xl" role="tablist">
                        <button type="button" data-login-tab="email" role="tab"
                            class="login-tab px-4 py-2 text-sm font-semibold rounded-lg transition duration-150">
                            E-mail
                        </button>
                        <button type="button" data-login-tab="matricula" role="tab"
                            class="login-tab px-4 py-2 text-sm font-semibold rounded-lg transition duration-150">
                            Matrícula
                        </button>
                    </div>

                    <!-- Campo E-mail -->
                    <div class="mb-5" data-login-field="email" @if($loginType !== 'email') hidden @endif>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
                        <input type="email" id="email" name="email" required autocomplete="email" value="{{ old('email') }}"
                            @disabled($loginType !== 'email')
                            placeholder="seu.email@exemplo.com"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:placeholder-gray-400">
                    </div>

                    <!-- Campo Matrícula -->
                    <div class="mb-5" data-login-field="matricula" @if($loginType !== 'matricula') hidden @endif>
                        <label for="matricula" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Matrícula</label>
                        <input type="text" id="matricula" name="matricula" required autocomplete="username"
                            maxlength="5" inputmode="numeric" value="{{ old('matricula') }}"
                            @disabled($loginType !== 'matricula')
                            placeholder="12345"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:placeholder-gray-400">
                    </div>

                    <!-- Campo Senha -->
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Senha</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            placeholder="••••••••"
                            class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition duration-150 text-base bg-white dark:bg-gray-700 text-gray-900 dark:text-white dark:placeholder-gray-400">
                    </div>

                @if ($errors->has('email') || $errors->has('matricula') || $errors->has('password'))
                     <!-- ÁREA DE MENSAGEM DE ERRO -->
                    <div id="login-error-message"
                        class="my-4 p-3 text-sm text-red-800 dark:text-red-300 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded-lg text-center"
                        role="alert">
                        {{ $errors->first($loginType) ?: $errors->first('password') }}
                    </div>
                @endif
                <!-- FIM DA ÁREA DE ERRO -->


                    {{-- <!-- Opções Adicionais (Lembrar-me e Esqueceu a Senha) -->
                    <div class="flex items-center justify-between mb-8">

                        <!-- Lembrar-me -->
                        <div class="flex items-center">
                            <input id="remember_me" type="checkbox" class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                            <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                                Lembrar-me
                            </label>
                        </div>

                        <!-- Esqueceu a Senha -->
                        <a href="#" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 transition duration-150">
                            Esqueceu a Senha?
                        </a>
                    </div> --}}

                    <!-- Botão de Login (Design de Alto Contraste) -->
                    <button type="submit"
                            class="w-full px-4 py-3 text-white rounded-xl font-extrabold text-lg shadow-lg hover:bg-indigo-700 transition duration-150 transform hover:scale-[1.01] focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            style="background: #A00001;">
                        Entrar
                    </button>

                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Ainda não tem uma conta?
                            <a href="/register" class="font-medium transition duration-150" style="color: #A00001;">
                                Registre-se
                            </a>
                        </p>
                    </div>
                </form>
            </div>

        </div>

        <script>
            (function () {
                const form = document.getElementById('login-form');
                if (!form) return;

                const hidden = document.getElementById('login_type');
                const tabs = form.querySelectorAll('[data-login-tab]');
                const fields = form.querySelectorAll('[data-login-field]');

                const ACTIVE = ['bg-white', 'dark:bg-gray-800', 'shadow', 'text-gray-900', 'dark:text-white'];
                const IDLE = ['text-gray-500', 'dark:text-gray-400'];

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
    </x-slot>
</x-guest-layout>

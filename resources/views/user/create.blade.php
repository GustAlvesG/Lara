<x-app-layout>
<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        @include('partials.alerts')

        <form action="{{ route('users.store') }}" method="POST">
            @csrf

            <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <a href="{{ route('users.index') }}" class="p-2 bg-surface rounded-xl shadow-card text-ink-3 hover:text-grena-ink border border-line transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                    </a>
                    <div>
                        <h1 class="font-display text-2xl font-semibold tracking-tight text-ink">Novo Usuário</h1>
                        <p class="text-ink-2 font-medium">Cadastre um novo membro e defina suas permissões de acesso.</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('users.index') }}" class="px-6 py-3 bg-surface text-ink rounded-xl font-bold shadow-card hover:bg-subtle border border-line transition">
                        Cancelar
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-grena text-white rounded-xl font-bold shadow-card hover:bg-grena-hover transition duration-150 transform hover:scale-[1.02]">
                        Criar Usuário
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-surface rounded-2xl shadow-pop p-6 border border-line text-center">
                        <div class="relative inline-block mb-4">
                            <div class="h-24 w-24 rounded-full bg-grena-tint text-3xl font-bold flex items-center justify-center text-grena-ink shadow-card">
                                ?
                            </div>
                            <span class="absolute bottom-1 right-1 h-6 w-6 bg-ok border-4 border-white rounded-full"></span>
                        </div>

                        <h2 class="text-xl font-bold text-ink">Novo Usuário</h2>
                        <p class="text-sm text-ink-2 mb-6">Preencha os dados ao lado</p>

                        <div class="text-left">
                            <label for="status" class="block text-xs font-bold text-ink-3 uppercase mb-1 tracking-wider">Estado da Conta</label>
                            <select name="status" id="status" class="w-full px-4 py-2 bg-subtle border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none font-semibold text-ink">
                                <option value="1" selected>Ativo</option>
                                <option value="2">Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="bg-grena rounded-2xl shadow-pop p-6 text-white">
                        <div class="flex items-center mb-3">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            <h3 class="font-bold uppercase text-xs tracking-widest">Segurança</h3>
                        </div>
                        <p class="text-sm text-white/80">
                            Crie uma senha temporária para o usuário. Recomenda-se que o mesmo altere sua senha no primeiro acesso.
                        </p>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                        <div class="p-6 border-b border-line bg-subtle">
                            <h2 class="text-lg font-bold text-ink">Dados Pessoais</h2>
                        </div>

                        <div class="px-6 pb-6 grid grid-cols-1 md:grid-cols-2 gap-6 pt-6">
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-bold text-ink mb-1">Nome Completo</label>
                                <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
                                @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-bold text-ink mb-1">Endereço de E-mail</label>
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
                                @error('email')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="matricula" class="block text-sm font-bold text-ink mb-1">Matrícula</label>
                                <input type="text" name="matricula" id="matricula" value="{{ old('matricula') }}" maxlength="5"
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink"
                                    placeholder="Ex: 00123">
                                <p class="mt-1 text-xs text-ink-2">Usada para vincular ao Banco de Horas.</p>
                                @error('matricula')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>

                        </div>
                    </div>

                    <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                        <div class="p-6 border-b border-line bg-subtle">
                            <h2 class="text-lg font-bold text-ink">Setores</h2>
                            <p class="text-xs text-ink-2">O acesso vem dos setores. Sem setor, a pessoa enxerga só o que é de todo mundo logado. Permissões individuais ficam na edição, depois de criar.</p>
                        </div>
                        <div class="px-6 pb-2">
                            @include('user.partials.sectors-select', ['sectors' => $sectors, 'current' => []])
                        </div>
                    </div>

                    <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                        <div class="p-6 border-b border-line bg-subtle">
                            <h2 class="text-lg font-bold text-ink">Senha de Acesso</h2>
                            <p class="text-xs text-ink-2">Defina uma senha inicial para o novo usuário.</p>
                        </div>

                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="password" class="block text-sm font-bold text-ink mb-1">Senha</label>
                                <input type="password" name="password" id="password" autocomplete="new-password" required
                                    placeholder="••••••••"
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
                                @error('password')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-bold text-ink mb-1">Confirmar Senha</label>
                                <input type="password" name="password_confirmation" id="password_confirmation" required
                                    placeholder="••••••••"
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink">
                            </div>
                        </div>
                    </div>

                    <div class="bg-surface rounded-2xl shadow-pop border border-line overflow-hidden">
                        <div class="p-6 border-b border-line bg-subtle">
                            <h2 class="text-lg font-bold text-ink">PIN de Assinatura (Tablet)</h2>
                            <p class="text-xs text-ink-2">6 dígitos usados no Kiosk de contratos: destrava a sessão e confirma cada assinatura. Opcional.</p>
                        </div>

                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="pin" class="block text-sm font-bold text-ink mb-1">PIN (6 dígitos)</label>
                                <input type="text" name="pin" id="pin" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="off"
                                    value="{{ old('pin') }}" placeholder="••••••"
                                    class="w-full px-4 py-2 border border-line rounded-lg focus:ring-2 focus:ring-grena-tint outline-none transition bg-surface text-ink tracking-[0.5em] font-mono">
                                @error('pin')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

</x-app-layout>
